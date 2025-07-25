<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeAllocation extends Model
{
    protected $fillable = [
        'income_id',
        'category_id',
        'type',
        'amount',
    ];

    /**
     * Relazione con l’entrata principale.
     */
    public function income()
    {
        return $this->belongsTo(Income::class);
    }

    /**
     * Relazione con la categoria di budget.
     */
    public function category()
    {
        return $this->belongsTo(BudgetCategory::class);
    }
}
