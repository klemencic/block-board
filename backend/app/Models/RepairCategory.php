<?php

namespace App\Models;

use App\Enums\Payer;
use Illuminate\Database\Eloquent\Model;

class RepairCategory extends Model
{
    protected $fillable = ['name', 'default_payer'];

    protected function casts(): array
    {
        return ['default_payer' => Payer::class];
    }
}
