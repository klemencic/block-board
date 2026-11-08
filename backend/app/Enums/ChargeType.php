<?php

namespace App\Enums;

enum ChargeType: string
{
    case Rent = 'rent';
    case Utility = 'utility';
    case Booking = 'booking';
    case Repair = 'repair';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'najemnina',
            self::Utility => 'stroški',
            self::Booking => 'rezervacija',
            self::Repair => 'popravilo',
            self::Other => 'drugo',
        };
    }
}
