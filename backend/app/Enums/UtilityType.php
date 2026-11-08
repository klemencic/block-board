<?php

namespace App\Enums;

enum UtilityType: string
{
    case Electricity = 'electricity';
    case Water = 'water';
    case Heating = 'heating';
    case Waste = 'waste';
    case Internet = 'internet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => 'Elektrika',
            self::Water => 'Voda',
            self::Heating => 'Ogrevanje',
            self::Waste => 'Odpadki',
            self::Internet => 'Internet',
            self::Other => 'Drugo',
        };
    }
}
