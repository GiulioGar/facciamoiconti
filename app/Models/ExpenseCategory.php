<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'expense_categories';

    // Mass assignment
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    /**
     * Le spese che appartengono a questa categoria
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
