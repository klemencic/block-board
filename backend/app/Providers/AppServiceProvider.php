<?php

namespace App\Providers;

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
        //
    }
}
