<?php

namespace Database\Factories;

use App\Enums\LeaseStatus;
use App\Models\Apartment;
use App\Models\Lease;
use App\Models\LeaseTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'apartment_id' => Apartment::factory(),
            'starts_on' => now()->startOfMonth()->subMonths(3)->toDateString(),
            'ends_on' => null,
            'notice_period_days' => 30,
            'monthly_rent_cents' => 60000,
            'deposit_cents' => 120000,
            'status' => LeaseStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => LeaseStatus::Active]);
    }

    /**
     * Doda najemnike z deleži (vsota je enaka najemnini).
     *
     * @param  list<int>  $shares  deleži v centih
     */
    public function withTenants(array $shares = [60000]): static
    {
        return $this->state(['monthly_rent_cents' => array_sum($shares)])
            ->afterCreating(function (Lease $lease) use ($shares) {
                foreach ($shares as $share) {
                    LeaseTenant::create([
                        'lease_id' => $lease->id,
                        'user_id' => User::factory()->create()->id,
                        'rent_share_cents' => $share,
                        'moved_in_on' => $lease->starts_on,
                    ]);
                }
            });
    }
}
