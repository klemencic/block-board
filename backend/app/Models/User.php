<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class, 'memberships')->withPivot('role')->withTimestamps();
    }

    public function hasRole(Building|int $building, Role $role): bool
    {
        $buildingId = $building instanceof Building ? $building->id : $building;

        return $this->memberships()
            ->where('building_id', $buildingId)
            ->where('role', $role->value)
            ->exists();
    }

    public function isLandlordOf(Building|int $building): bool
    {
        return $this->hasRole($building, Role::Landlord);
    }

    public function isMemberOf(Building|int $building): bool
    {
        $buildingId = $building instanceof Building ? $building->id : $building;

        return $this->memberships()->where('building_id', $buildingId)->exists();
    }
}
