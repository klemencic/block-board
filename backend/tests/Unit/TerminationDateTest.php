<?php

use App\Models\Lease;
use App\Services\LeaseLifecycle;
use Carbon\CarbonImmutable;

function terminationFor(string $noticeDate, int $days, ?string $endsOn = null): string
{
    $lease = new Lease(['notice_period_days' => $days, 'ends_on' => $endsOn]);

    return app(LeaseLifecycle::class)
        ->terminationDate($lease, CarbonImmutable::parse($noticeDate))
        ->toDateString();
}

it('prišteje odpovedni rok v dneh k datumu odpovedi', function () {
    // Primer iz US-02: odpoved 1. 3. 2027 z rokom 30 dni pomeni prenehanje 31. 3. 2027.
    expect(terminationFor('2027-03-01', 30))->toBe('2027-03-31');
});

it('pravilno upošteva dolžine mesecev in prestopna leta', function (string $notice, int $days, string $expected) {
    expect(terminationFor($notice, $days))->toBe($expected);
})->with([
    'februar v navadnem letu' => ['2027-02-15', 30, '2027-03-17'],
    'februar v prestopnem letu' => ['2028-02-15', 30, '2028-03-16'],
    'čez 29. februar' => ['2028-02-28', 1, '2028-02-29'],
    'čez konec leta' => ['2026-12-15', 30, '2027-01-14'],
    'brez odpovednega roka' => ['2027-05-10', 0, '2027-05-10'],
]);

it('pri najemu za določen čas datum prenehanja ni po datumu konca', function () {
    expect(terminationFor('2027-03-20', 30, '2027-03-31'))->toBe('2027-03-31')
        ->and(terminationFor('2027-03-01', 30, '2027-06-30'))->toBe('2027-03-31');
});
