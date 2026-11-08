<?php

namespace App\Enums;

enum Role: string
{
    case Landlord = 'landlord';
    case Tenant = 'tenant';
    case Contractor = 'contractor';

    public function label(): string
    {
        return match ($this) {
            self::Landlord => 'lastnik',
            self::Tenant => 'najemnik',
            self::Contractor => 'izvajalec',
        };
    }
}
