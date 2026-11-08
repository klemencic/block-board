<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillShare extends Model
{
    protected $fillable = ['utility_bill_id', 'apartment_id', 'lease_tenant_id', 'days', 'amount_cents'];

    protected function casts(): array
    {
        return ['days' => 'integer', 'amount_cents' => 'integer'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(UtilityBill::class, 'utility_bill_id');
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function leaseTenant(): BelongsTo
    {
        return $this->belongsTo(LeaseTenant::class);
    }

    public function charge(): HasOne
    {
        return $this->hasOne(Charge::class);
    }

    /** Delež praznega stanovanja, ki ostane lastniku. */
    public function isLandlordShare(): bool
    {
        return $this->lease_tenant_id === null;
    }
}
