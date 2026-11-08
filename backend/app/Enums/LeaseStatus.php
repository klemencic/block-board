<?php

namespace App\Enums;

enum LeaseStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case InNotice = 'in_notice';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'osnutek',
            self::Active => 'aktiven',
            self::InNotice => 'v odpovednem roku',
            self::Ended => 'zaključen',
        };
    }

    /** Stanja, v katerih najem zaseda stanovanje. */
    public static function occupying(): array
    {
        return [self::Active->value, self::InNotice->value];
    }
}
