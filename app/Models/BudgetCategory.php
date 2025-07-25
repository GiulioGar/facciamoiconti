<?php

// app/Models/BudgetCategory.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetCategory extends Model
{
    protected $fillable = ['name','slug','sort_order'];

    // In futuro: relazioni con income_allocations
    public function allocations()
    {
        return $this->hasMany(IncomeAllocation::class, 'category_id');
    }
}

