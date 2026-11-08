<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    /** Lastnik vidi vsa obvestila stavbe, najemnik samo obvestila za stavbo ali svoje nadstropje (US-14). */
    public function view(User $user, Announcement $announcement): bool
    {
        if ($user->isLandlordOf($announcement->building_id)) {
            return true;
        }

        return Announcement::visibleToTenant($user, $announcement->building_id)->whereKey($announcement->id)->exists();
    }
}
