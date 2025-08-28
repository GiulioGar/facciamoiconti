<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantaBudgetState extends Model
{
    protected $table = 'fanta_budget_state';
    protected $fillable = ['anchor_remaining','caps','spent_at_anchor','open_roles'];
    protected $casts = [
        'caps'            => 'array',
        'spent_at_anchor' => 'array',
        'open_roles'      => 'array',
    ];
}
