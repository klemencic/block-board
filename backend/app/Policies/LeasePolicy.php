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

    /** Odpoved poda najemnik, ki še živi v stanovanju. */
    public function giveNotice(User $user, Lease $lease): bool
    {
        return $lease->currentTenants()->where('user_id', $user->id)->exists();
    }

    public function activate(User $user, Lease $lease): bool
    {
        return $user->isLandlordOf($lease->buildingId());
    }
}
