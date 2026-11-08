<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    /** Lastnik stavbe ali najemnik, ki je naveden na najemu. */
    public function view(User $user, Lease $lease): bool
    {
        return $user->isLandlordOf($lease->buildingId()) || $lease->hasTenant($user);
    }

    public function activate(User $user, Lease $lease): bool
    {
        return $user->isLandlordOf($lease->buildingId());
    }
}
