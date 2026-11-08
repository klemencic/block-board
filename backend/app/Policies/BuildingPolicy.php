<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;

class BuildingPolicy
{
    /** Vsak član stavbe (lastnik, najemnik, izvajalec). */
    public function view(User $user, Building $building): bool
    {
        return $user->isMemberOf($building);
    }

    /** Upravljanje stavbe: samo lastnik. */
    public function manage(User $user, Building $building): bool
    {
        return $user->isLandlordOf($building);
    }
}
