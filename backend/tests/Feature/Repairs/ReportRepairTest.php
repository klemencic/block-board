<?php

use App\Enums\LeaseStatus;
use App\Enums\Payer;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Attachment;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Membership;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\SharedSpace;
use App\Notifications\RepairReported;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();

    $this->building = Building::factory()->create();
    $this->landlord = memberOf($this->building, Role::Landlord);
    $this->heating = RepairCategory::create(['name' => 'Ogrevanje in topla voda', 'default_payer' => Payer::Landlord]);
    $this->damage = RepairCategory::create(['name' => 'Poškodba po krivdi najemnika', 'default_payer' => Payer::Tenant]);
    $this->space = SharedSpace::create([
        'building_id' => $this->building->id, 'name' => 'Pralnica', 'open_from' => '07:00', 'open_to' => '22:00', 'max_hours_per_week' => 6,
    ]);

    $this->apartment = Apartment::factory()->for($this->building)->create(['label' => '2C']);
    $lease = Lease::factory()->for($this->apartment)->withTenants([30000, 30000])->create(['status' => LeaseStatus::Active]);
    [$this->jan, $this->nik] = [$lease->tenants[0]->user, $lease->tenants[1]->user];
    $this->otherApartment = Apartment::factory()->for($this->building)->create(['label' => '3B']);
    $this->other = Lease::factory()->for($this->otherApartment)->withTenants()->create(['status' => LeaseStatus::Active])->tenants[0]->user;

    foreach ([$this->jan, $this->nik, $this->other] as $user) {
        Membership::create(['user_id' => $user->id, 'building_id' => $this->building->id, 'role' => Role::Tenant]);
    }
});

function reportRepair($user, array $overrides = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($user)->post('/api/buildings/'.test()->building->id.'/repair-requests', [
        'title' => 'Bojler ne greje vode',
        'description' => 'Od sinoči iz pip teče samo hladna voda.',
        'category_id' => test()->heating->id,
        'apartment_id' => test()->apartment->id,
        ...$overrides,
    ], ['Accept' => 'application/json']);
}

it('shrani prijavo v stanju prijavljeno s privzetim plačnikom iz vrste okvare', function () {
    reportRepair($this->jan)
        ->assertCreated()
        ->assertJsonPath('data.status', 'reported')
        ->assertJsonPath('data.status_label', 'Prijavljeno')
        ->assertJsonPath('data.payer', 'landlord')
        ->assertJsonPath('data.default_payer', 'landlord')
        ->assertJsonPath('data.number', 'R-'.str_pad((string) RepairRequest::sole()->id, 4, '0', STR_PAD_LEFT))
        ->assertJsonPath('data.timeline.0.user', $this->jan->name)
        ->assertJsonPath('data.timeline.1.kind', 'payer')
        ->assertJsonPath('data.timeline.1.user', 'Sistem');

    reportRepair($this->jan, ['category_id' => $this->damage->id])->assertJsonPath('data.payer', 'tenant');
});

it('lastnik prejme e-pošto s povezavo na prijavo', function () {
    reportRepair($this->jan);

    Notification::assertSentTo($this->landlord, RepairReported::class, function (RepairReported $n) {
        return str_ends_with($n->toMail($this->landlord)->actionUrl, '/okvare/'.$n->repair->id);
    });
});

it('obvezna so naslov, opis, vrsta in lokacija', function () {
    $this->actingAs($this->jan)->postJson("/api/buildings/{$this->building->id}/repair-requests", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'description', 'category_id', 'apartment_id']);
});

it('najemnik izbere samo svoje stanovanje z aktivnim najemom ali skupni prostor', function () {
    reportRepair($this->jan, ['apartment_id' => $this->otherApartment->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['apartment_id' => 'Okvaro lahko prijavite samo za stanovanje, v katerem imate aktiven najem.']);

    reportRepair($this->jan, ['apartment_id' => null, 'shared_space_id' => $this->space->id])
        ->assertCreated()
        ->assertJsonPath('data.location.label', 'Pralnica');

    $foreignSpace = SharedSpace::create(['building_id' => Building::factory()->create()->id, 'name' => 'X', 'open_from' => '07:00', 'open_to' => '22:00', 'max_hours_per_week' => 6]);
    reportRepair($this->jan, ['apartment_id' => null, 'shared_space_id' => $foreignSpace->id])
        ->assertUnprocessable()->assertJsonValidationErrors('shared_space_id');
});

it('shrani do 5 fotografij JPG ali PNG kot priloge prijave', function () {
    reportRepair($this->jan, ['photos' => [
        UploadedFile::fake()->create('IMG_0381.jpg', 800, 'image/jpeg'),
        UploadedFile::fake()->create('IMG_0382.png', 500, 'image/png'),
    ]])->assertCreated()->assertJsonCount(2, 'data.photos');

    $attachment = Attachment::first();
    expect(Attachment::count())->toBe(2)
        ->and($attachment->attachable_type)->toBe('repair_request')
        ->and($attachment->context)->toBe('report');
    Storage::disk('local')->assertExists($attachment->path);
});

it('zavrne več kot 5 fotografij, napačno obliko in datoteke nad 10 MB z razlogom', function () {
    $photos = array_map(fn ($i) => UploadedFile::fake()->create("p$i.jpg", 10, 'image/jpeg'), range(1, 6));
    reportRepair($this->jan, ['photos' => $photos])
        ->assertUnprocessable()->assertJsonValidationErrors(['photos' => 'Priložite lahko največ 5 fotografij.']);

    reportRepair($this->jan, ['photos' => [UploadedFile::fake()->create('racun.pdf', 100, 'application/pdf')]])
        ->assertUnprocessable()->assertJsonValidationErrors(['photos.0' => 'Datoteka »racun.pdf« ni v obliki JPG ali PNG.']);

    reportRepair($this->jan, ['photos' => [UploadedFile::fake()->create('velika.jpg', 10241, 'image/jpeg')]])
        ->assertUnprocessable()->assertJsonValidationErrors(['photos.0' => 'Datoteka »velika.jpg« je večja od 10 MB.']);

    expect(RepairRequest::count())->toBe(0);
});

it('najemnik vidi svoje prijave in prijave svojega stanovanja, ne pa drugih stanovanj', function () {
    $mine = RepairRequest::find(reportRepair($this->jan)->json('data.id'));
    $otherRepair = RepairRequest::find(reportRepair($this->other, ['apartment_id' => $this->otherApartment->id])->json('data.id'));

    // Sostanovalec vidi prijavo svojega stanovanja s časovnico.
    $this->actingAs($this->nik)->getJson("/api/buildings/{$this->building->id}/repair-requests")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    $this->actingAs($this->nik)->getJson("/api/repair-requests/{$mine->id}")
        ->assertOk()->assertJsonPath('data.timeline.0.to_status', 'reported');

    $this->actingAs($this->jan)->getJson("/api/repair-requests/{$otherRepair->id}")->assertForbidden();
    $this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/repair-requests")->assertJsonCount(2, 'data');
});

it('fotografija je dostopna samo uporabnikom, ki vidijo prijavo', function () {
    reportRepair($this->jan, ['photos' => [UploadedFile::fake()->create('IMG.jpg', 10, 'image/jpeg')]]);
    $attachment = Attachment::sole();

    $this->actingAs($this->nik)->get("/api/attachments/{$attachment->id}")->assertOk();
    $this->actingAs($this->other)->getJson("/api/attachments/{$attachment->id}")->assertForbidden();
});

it('obrazec vrne privzetega plačnika za vsako vrsto in samo dovoljene lokacije', function () {
    $form = $this->actingAs($this->jan)->getJson("/api/buildings/{$this->building->id}/repair-form")->assertOk()->json('data');

    expect($form['categories'][0])->toMatchArray(['name' => 'Ogrevanje in topla voda', 'default_payer_label' => 'lastnik'])
        ->and(collect($form['apartments'])->pluck('label')->all())->toBe(['2C'])
        ->and(collect($form['shared_spaces'])->pluck('name')->all())->toBe(['Pralnica']);
});

it('izvajalec okvare ne more prijaviti', function () {
    $contractor = memberOf($this->building, Role::Contractor);

    reportRepair($contractor)->assertForbidden();
});
