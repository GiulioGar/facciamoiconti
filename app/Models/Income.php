<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    protected $fillable = [
        'user_id',
        'family_id',
        'amount',
        'date',
        'description',
    ];

    /**
     * Legame con le allocazioni di budget (step successivo).
     */
    public function allocations()
    {
        return $this->hasMany(IncomeAllocation::class);
    }
}
