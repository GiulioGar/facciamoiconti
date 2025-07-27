<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    protected $fillable = [
        'user_id',
        'family_id',
        'category_id',
        'current_balance',
        'invested_balance',
    ];

    /**
     * Relazione: un investimento appartiene a una categoria
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InvestmentCategory::class, 'category_id');
    }
}
