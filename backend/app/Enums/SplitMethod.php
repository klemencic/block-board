<?php

namespace App\Enums;

/** Delitev položnice stavbe med stanovanja (US-07). */
enum SplitMethod: string
{
    case Area = 'area';
    case Persons = 'persons';
    case Equal = 'equal';

    public function label(): string
    {
        return match ($this) {
            self::Area => 'po površini',
            self::Persons => 'po številu oseb',
            self::Equal => 'na enake dele',
        };
    }
}
