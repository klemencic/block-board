<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\AddedToLease;
use App\Notifications\TenantInvitation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Poišče ali ustvari račun najemnika in mu omogoči dostop do najema (US-01, #95).
 */
class TenantInviter
{
    /**
     * @param  array{name: string, email: string, phone?: string|null}  $data
     */
    public function findOrCreate(array $data): User
    {
        $email = Str::lower(trim($data['email']));

        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        return User::create([
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            // Naključno geslo, ki ga nihče ne pozna. Najemnik si nastavi svoje prek povabila.
            'password' => Str::random(40),
        ]);
    }

    public function grantAccess(User $user, Building $building): void
    {
        Membership::firstOrCreate([
            'user_id' => $user->id,
            'building_id' => $building->id,
            'role' => Role::Tenant,
        ]);
    }

    /**
     * Novemu uporabniku pošlje povabilo s povezavo za nastavitev gesla (velja 7 dni),
     * obstoječemu pa samo obvestilo o dostopu do najema.
     */
    public function notify(User $user, Lease $lease, bool $isNewAccount): void
    {
        if ($isNewAccount) {
            $token = Password::broker('invites')->createToken($user);
            $user->notify(new TenantInvitation($lease, $token));

            return;
        }

        $user->notify(new AddedToLease($lease));
    }
}
