<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\User;

class ChargePolicy
{
    /** Najemnik vidi samo svoje obveznosti, lastnik vse v svoji stavbi (US-04). */
    public function view(User $user, Charge $charge): bool
    {
        $tenant = $charge->leaseTenant;

        return $tenant->user_id === $user->id || $user->isLandlordOf($tenant->lease->buildingId());
    }

    /** Plačilo označi samo lastnik stavbe (US-05). */
    public function markPaid(User $user, Charge $charge): bool
    {
        return $user->isLandlordOf($charge->leaseTenant->lease->buildingId());
    }
}
