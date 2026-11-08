<?php

namespace App\Enums;

enum RepairStatus: string
{
    case Reported = 'reported';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Assigned = 'assigned';
    case Scheduled = 'scheduled';
    case Done = 'done';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Prijavljeno',
            self::Confirmed => 'Potrjeno',
            self::Rejected => 'Zavrnjeno',
            self::Assigned => 'Dodeljeno',
            self::Scheduled => 'Dogovorjen termin',
            self::Done => 'Opravljeno',
            self::Closed => 'Zaključeno',
        };
    }

    /** Odprte prijave (US-13): niso zaključene ali zavrnjene. */
    public static function open(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => ! in_array($s, [self::Closed, self::Rejected], true))
        ));
    }
}
