<?php

namespace Database\Seeders;

use App\Enums\Payer;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Membership;
use App\Models\RepairCategory;
use App\Models\SharedSpace;
use App\Models\User;
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

        $tenant = $this->user('Jan Potočnik', 'jan@blockboard.test', '040 123 456');
        $this->member($tenant, $building, Role::Tenant);
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
