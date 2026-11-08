<?php

use Illuminate\Support\Facades\Schedule;

// Razporejevalnik teče v storitvi worker (php artisan schedule:work).

// Na dan prenehanja ali datum konca se najem zaključi (US-02).
Schedule::command('leases:end-due')->dailyAt('00:05');

// Obveznosti za najemnino (US-03). Dnevni zagon je idempotenten in ujame tudi najeme,
// ki se začnejo med mesecem (sorazmerni del na dan začetka).
Schedule::command('charges:generate')->dailyAt('00:10');

// Prvi dan v mesecu najemniki prejmejo povzetek obveznosti (US-03).
Schedule::command('charges:generate --statements')->monthlyOn(1, '07:00');
