<?php

namespace App\Providers;

use App\Enums\ChargeType;
use App\Models\BillShare;
use App\Models\Charge;
use App\Services\ChargeSourceResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Moduli (položnice, rezervacije, okvare) registrirajo, kako se prikaže izvor obveznosti.
        $this->app->singleton(ChargeSourceResolver::class);
    }

    public function boot(): void
    {
        $sources = $this->app->make(ChargeSourceResolver::class);

        // Položnica z izračunom deleža; deleži drugih stanovanj niso vključeni (US-06, US-07).
        $sources->register(ChargeType::Utility, function (Charge $charge): ?array {
            $share = BillShare::with('bill', 'apartment')->find($charge->bill_share_id);
            if (! $share) {
                return null;
            }

            $apartmentShares = BillShare::where('utility_bill_id', $share->utility_bill_id)
                ->where('apartment_id', $share->apartment_id)
                ->get();

            return [
                'bill' => [
                    'id' => $share->bill->id,
                    'type_label' => $share->bill->type->label(),
                    'amount_cents' => $share->bill->amount_cents,
                    'period_start' => $share->bill->period_start->toDateString(),
                    'period_end' => $share->bill->period_end->toDateString(),
                    'scope' => $share->bill->isForBuilding() ? 'building' : 'apartment',
                    'split_method_label' => $share->bill->split_method?->label(),
                ],
                'apartment' => [
                    'label' => $share->apartment->label,
                    'share_cents' => $apartmentShares->sum('amount_cents'),
                    'days' => $apartmentShares->sum('days'),
                ],
                'tenant' => [
                    'days' => $share->days,
                    'amount_cents' => $share->amount_cents,
                ],
            ];
        });
    }
}
