<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class Dates
{
    private const MONTHS = [
        1 => 'januar', 'februar', 'marec', 'april', 'maj', 'junij',
        'julij', 'avgust', 'september', 'oktober', 'november', 'december',
    ];

    public static function monthName(CarbonInterface $date): string
    {
        return self::MONTHS[$date->month];
    }

    /** »maj 2027« */
    public static function period(CarbonInterface $date): string
    {
        return self::monthName($date).' '.$date->year;
    }

    /** »16. 5. 2027« */
    public static function format(CarbonInterface $date): string
    {
        return $date->format('j. n. Y');
    }

    /** Prvi dan naslednjega obračunskega meseca glede na podani dan. */
    public static function nextPeriod(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString())->startOfMonth()->addMonth();
    }

    /** Rok plačila v obračunskem mesecu: dan iz nastavitev stavbe, omejen na dolžino meseca. */
    public static function dueDate(CarbonInterface $period, int $dueDay): CarbonImmutable
    {
        $period = CarbonImmutable::parse($period->toDateString())->startOfMonth();

        return $period->setDay(min($dueDay, $period->daysInMonth));
    }
}
