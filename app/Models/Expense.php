<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'expenses';

    // Mass assignment
    protected $fillable = [
        'description',
        'amount',
        'date',
        'note',
        'expense_category_id',
        'budget_category_id',
        'family_id',
        'user_id',
    ];

    /**
     * Categoria di spesa
     */
    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * Budget di riferimento
     */
    public function budgetCategory()
    {
        return $this->belongsTo(BudgetCategory::class);
    }

    /**
     * Famiglia proprietaria
     */
    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Utente che ha registrato la spesa
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
