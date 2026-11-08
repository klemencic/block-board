<?php

use Illuminate\Support\Facades\Schedule;

// Razporejevalnik teče v storitvi worker (php artisan schedule:work).

// Na dan prenehanja ali datum konca se najem zaključi (US-02).
Schedule::command('leases:end-due')->dailyAt('00:05');
