<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Relazione: una categoria ha molti investimenti
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'category_id');
    }
}
