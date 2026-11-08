<?php

namespace App\Policies;

use App\Models\RepairRequest;
use App\Models\User;

class RepairRequestPolicy
{
    /**
     * Lastnik vidi vse prijave stavbe, izvajalec samo dodeljene, prijavitelj svojo,
     * sostanovalci pa prijave svojega stanovanja (US-09, US-11).
     */
    public function view(User $user, RepairRequest $repair): bool
    {
        return RepairRequest::visibleTo($user, $repair->building_id)->whereKey($repair->id)->exists();
    }

    /** Odločitve o prijavi (potrditev, plačnik, izvajalec, zaključek) sprejema samo lastnik. */
    public function manage(User $user, RepairRequest $repair): bool
    {
        return $user->isLandlordOf($repair->building_id);
    }

    /** Korake izvedbe opravi samo dodeljeni izvajalec. */
    public function perform(User $user, RepairRequest $repair): bool
    {
        return $repair->contractor_id === $user->id;
    }
}
