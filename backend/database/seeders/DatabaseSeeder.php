<?php

namespace Database\Seeders;

use App\Enums\LeaseStatus;
use App\Enums\Payer;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\LeaseTenant;
use App\Models\Membership;
use App\Models\RepairCategory;
use App\Models\SharedSpace;
use App\Models\User;
use App\Services\BillSplitter;
use App\Services\BookingService;
use App\Services\ChargeGenerator;
use App\Services\RepairWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo stavba »Stavba Lipa« s podatki, ki ustrezajo wireframom.
 * Vsi demo računi imajo geslo »geslo123«.
 */
class DatabaseSeeder extends Seeder
{
    public const PASSWORD = 'geslo123';

    public function run(): void
    {
        $building = Building::create([
            'name' => 'Stavba Lipa',
            'address' => 'Lipova ulica 12, Ljubljana',
            'rent_due_day' => 8,
        ]);

        $landlord = $this->user('Matej Lastnik', 'lastnik@blockboard.test', '041 000 001');
        $this->member($landlord, $building, Role::Landlord);

        foreach ($this->apartments() as [$label, $floor, $area, $rooms]) {
            Apartment::create([
                'building_id' => $building->id,
                'label' => $label,
                'floor' => $floor,
                'area_m2' => $area,
                'rooms' => $rooms,
            ]);
        }

        foreach ([
            ['Skupna soba', 500, '08:00', '22:00', 6],
            ['Igralnica', 0, '08:00', '20:00', 8],
            ['Pralnica', 200, '07:00', '22:00', 6],
        ] as [$name, $price, $from, $to, $max]) {
            SharedSpace::create([
                'building_id' => $building->id,
                'name' => $name,
                'price_per_hour_cents' => $price,
                'open_from' => $from,
                'open_to' => $to,
                'max_hours_per_week' => $max,
                'free_cancel_hours' => 24,
            ]);
        }

        foreach ([
            ['Ogrevanje in topla voda', Payer::Landlord],
            ['Vodovod in odtoki', Payer::Landlord],
            ['Elektrika', Payer::Landlord],
            ['Okna in vrata', Payer::Landlord],
            ['Gospodinjski aparati', Payer::Landlord],
            ['Poškodba po krivdi najemnika', Payer::Tenant],
            ['Drobna popravila in potrošni material', Payer::Tenant],
        ] as [$name, $payer]) {
            RepairCategory::create(['name' => $name, 'default_payer' => $payer]);
        }

        foreach ([
            ['Vodoinštalacije Breznik', 'breznik@blockboard.test', '041 222 333'],
            ['Elektro Hribar', 'hribar@blockboard.test', '041 444 555'],
        ] as [$name, $email, $phone]) {
            $this->member($this->user($name, $email, $phone), $building, Role::Contractor);
        }

        $this->seedLeases($building);
        $this->seedCharges($building);
        $this->seedBills($building, $landlord);
        $this->seedBookings($building);
        $this->seedRepairs($building, $landlord);
    }

    /**
     * Prijave okvar kot v wireframih: 4 odprte (1B, 2C, 3A, 4A), od tega 1 čaka na potrditev (2C, WF-03).
     */
    private function seedRepairs(Building $building, User $landlord): void
    {
        $workflow = app(RepairWorkflow::class);
        $user = fn (string $email) => User::where('email', $email)->firstOrFail();
        $apartment = fn (string $label) => $building->apartments()->where('label', $label)->value('id');
        $category = fn (string $name) => RepairCategory::where('name', $name)->value('id');
        $breznik = $user('breznik@blockboard.test');
        $hribar = $user('hribar@blockboard.test');

        $report = fn (string $email, string $label, string $title, string $description, string $cat) => $workflow->report($building, $user($email), [
            'title' => $title, 'description' => $description, 'category_id' => $category($cat), 'apartment_id' => $apartment($label),
        ]);

        $report('jan@blockboard.test', '2C', 'Bojler ne greje vode',
            'Od sinoči iz pip teče samo hladna voda. Na bojlerju v kopalnici sveti rdeča lučka, varovalka v omarici ni izpadla.',
            'Ogrevanje in topla voda');

        $leak = $report('marko@blockboard.test', '1B', 'Kaplja pipa v kuhinji', 'Pipa nad koritom ves čas kaplja, tudi ko je zaprta do konca.', 'Vodovod in odtoki');
        $workflow->confirm($leak, $landlord);
        $workflow->assign($leak, $landlord, $breznik);

        $socket = $report('maja@blockboard.test', '3A', 'Vtičnica v dnevni sobi ne deluje', 'Vtičnica ob oknu ne deluje, ostale v sobi delujejo.', 'Elektrika');
        $workflow->confirm($socket, $landlord);
        $workflow->assign($socket, $landlord, $hribar);

        $window = $report('nina@blockboard.test', '4A', 'Okno v spalnici se ne zapre', 'Kljuka se zatika, okna ni mogoče popolnoma zapreti.', 'Okna in vrata');
        $workflow->confirm($window, $landlord);
    }

    /**
     * Prihajajoče rezervacije, da koledar skupnih prostorov ni prazen (WF-02).
     */
    private function seedBookings(Building $building): void
    {
        $service = app(BookingService::class);
        $room = $building->sharedSpaces()->where('name', 'Skupna soba')->first();
        $tenant = fn (string $email) => LeaseTenant::whereHas('user', fn ($q) => $q->where('email', $email))->first();
        $day = CarbonImmutable::tomorrow();

        foreach ([
            ['nik@blockboard.test', 0, '18:00', '20:00'],
            ['peter@blockboard.test', 0, '10:00', '11:30'],
            ['maja@blockboard.test', 1, '16:30', '18:00'],
            ['ana@blockboard.test', 2, '19:00', '22:00'],
            ['gregor@blockboard.test', 3, '10:00', '13:00'],
        ] as [$email, $offset, $from, $to]) {
            $date = $day->addDays($offset)->toDateString();
            $service->book($room, $tenant($email), CarbonImmutable::parse("$date $from"), CarbonImmutable::parse("$date $to"));
        }
    }

    /**
     * Položnice za pretekli mesec kot v tabeli »Zadnje položnice« (WF-04).
     */
    private function seedBills(Building $building, User $landlord): void
    {
        $splitter = app(BillSplitter::class);
        $month = CarbonImmutable::today()->startOfMonth()->subMonth();
        $period = ['period_start' => $month->toDateString(), 'period_end' => $month->endOfMonth()->toDateString()];

        foreach ([
            ['type' => 'heating', 'amount_cents' => 126000, 'split_method' => 'area'],
            ['type' => 'water', 'amount_cents' => 42000, 'split_method' => 'persons'],
            ['type' => 'waste', 'amount_cents' => 12000, 'split_method' => 'equal'],
        ] as $bill) {
            $splitter->store($building, $landlord, [...$bill, ...$period, 'apartment_id' => null]);
        }

        $splitter->store($building, $landlord, [
            'type' => 'electricity', 'amount_cents' => 9000, ...$period,
            'apartment_id' => $building->apartments()->where('label', '2C')->value('id'),
        ]);
    }

    /**
     * Obveznosti za zadnje tri mesece. Pretekle so plačane, razen zamud kot v wireframu:
     * 2A (Luka Novak) 300,00 €, 3A (Maja Kos) 2 × 330,00 € = 660,00 €.
     */
    private function seedCharges(Building $building): void
    {
        $generator = app(ChargeGenerator::class);
        $today = CarbonImmutable::today();
        $unpaidMonths = ['luka@blockboard.test' => 1, 'maja@blockboard.test' => 2];

        foreach ([2, 1, 0] as $monthsAgo) {
            $period = $today->startOfMonth()->subMonths($monthsAgo);
            // Zadnji dan meseca, da nastanejo tudi sorazmerni deli za vselitve med mesecem.
            $date = $monthsAgo === 0 ? $today : $period->endOfMonth();

            Lease::occupying()
                ->whereHas('apartment', fn ($q) => $q->where('building_id', $building->id))
                ->get()
                ->each(fn (Lease $lease) => $generator->generateForLease($lease, $date));

            if ($monthsAgo === 0) {
                continue;
            }

            Charge::whereDate('period', $period)->with('leaseTenant.user')->get()
                ->each(function (Charge $charge) use ($unpaidMonths, $monthsAgo) {
                    if (($unpaidMonths[$charge->leaseTenant->user->email] ?? 0) >= $monthsAgo) {
                        return;
                    }
                    $charge->update(['paid_at' => $charge->due_date->copy()->subDays(2)]);
                });
        }
    }

    /**
     * Najemi kot v wireframu »Pregled stavbe«: 10 od 12 stanovanj je zasedenih,
     * 2B je v odpovednem roku, 1B in 4A se iztekata v naslednjih 60 dneh.
     */
    private function seedLeases(Building $building): void
    {
        $today = now()->startOfDay();
        $apartments = $building->apartments()->get()->keyBy('label');

        $leases = [
            // stanovanje, najemniki [ime, e-naslov, telefon], najemnina, začetek, konec, stanje
            ['1A', [['Ana Kovač', 'ana@blockboard.test', '040 111 001']], 52000, '-14 months', null, LeaseStatus::Active],
            ['1B', [['Marko Zupan', 'marko@blockboard.test', '040 111 002']], 42000, '-11 months', '+22 days', LeaseStatus::Active],
            ['2A', [['Luka Novak', 'luka@blockboard.test', '040 111 003'], ['Eva Horvat', 'eva@blockboard.test', '040 111 004']], 60000, '-20 months', null, LeaseStatus::Active],
            ['2B', [['Tina Krajnc', 'tina@blockboard.test', '040 111 005']], 45000, '-9 months', null, LeaseStatus::InNotice],
            ['2C', [['Jan Potočnik', 'jan@blockboard.test', '040 123 456'], ['Nik Golob', 'nik@blockboard.test', '040 111 006'], ['Sara Mlakar', 'sara@blockboard.test', '040 111 007']], 90000, '-4 months', '+10 months', LeaseStatus::Active],
            ['3A', [['Maja Kos', 'maja@blockboard.test', '040 111 008']], 33000, '-6 months', '+9 months', LeaseStatus::Active],
            ['3B', [['Peter Vidmar', 'peter@blockboard.test', '040 111 009']], 52000, '-30 months', null, LeaseStatus::Active],
            ['4A', [['Nina Bizjak', 'nina@blockboard.test', '040 111 010'], ['Rok Turk', 'rok@blockboard.test', '040 111 011']], 80000, '-23 months', '+37 days', LeaseStatus::Active],
            ['4B', [['Gregor Kralj', 'gregor@blockboard.test', '040 111 012']], 55000, '-8 months', null, LeaseStatus::Active],
            ['4C', [['Urška Petek', 'urska@blockboard.test', '040 111 013']], 48000, '-2 months', '+5 months', LeaseStatus::Active],
        ];

        foreach ($leases as [$label, $tenants, $rent, $start, $end, $status]) {
            $startsOn = $today->copy()->modify($start)->startOfMonth();
            $lease = Lease::create([
                'apartment_id' => $apartments[$label]->id,
                'starts_on' => $startsOn,
                'ends_on' => $end ? $today->copy()->modify($end) : null,
                'notice_period_days' => 30,
                'monthly_rent_cents' => $rent,
                'deposit_cents' => $rent * 2,
                'status' => $status,
                'notice_given_on' => $status === LeaseStatus::InNotice ? $today->copy()->subDays(23) : null,
                'terminates_on' => $status === LeaseStatus::InNotice ? $today->copy()->addDays(7) : null,
            ]);

            $share = intdiv($rent, count($tenants));
            foreach ($tenants as $i => [$name, $email, $phone]) {
                $user = $this->user($name, $email, $phone);
                $this->member($user, $building, Role::Tenant);

                LeaseTenant::create([
                    'lease_id' => $lease->id,
                    'user_id' => $user->id,
                    // Ostanek centov dobi prvi najemnik, da je vsota enaka najemnini.
                    'rent_share_cents' => $share + ($i === 0 ? $rent - $share * count($tenants) : 0),
                    // Sara Mlakar se je v 2C vselila pozneje (primer iz wireframa za položnice).
                    'moved_in_on' => $email === 'sara@blockboard.test' ? $startsOn->copy()->addMonth()->addDays(15) : $startsOn,
                ]);
            }
        }
    }

    /**
     * @return list<array{string, int, float, int}>
     */
    private function apartments(): array
    {
        return [
            ['1A', 0, 52.0, 2], ['1B', 0, 38.5, 1], ['1C', 0, 45.0, 2],
            ['2A', 1, 68.0, 3], ['2B', 1, 41.0, 1], ['2C', 1, 75.0, 3],
            ['3A', 2, 55.0, 2], ['3B', 2, 48.0, 2], ['3C', 2, 39.0, 1],
            ['4A', 3, 72.0, 3], ['4B', 3, 50.0, 2], ['4C', 3, 44.0, 2],
        ];
    }

    private function user(string $name, string $email, ?string $phone = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => self::PASSWORD,
        ]);
    }

    private function member(User $user, Building $building, Role $role): void
    {
        Membership::create(['user_id' => $user->id, 'building_id' => $building->id, 'role' => $role]);
    }
}
