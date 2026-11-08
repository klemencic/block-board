<?php

namespace App\Enums;

enum Payer: string
{
    case Landlord = 'landlord';
    case Tenant = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::Landlord => 'lastnik',
            self::Tenant => 'najemnik',
        };
    }
}
