<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialBalance extends Model
{
    use HasFactory;

    protected $fillable = [
    'user_id',
    'family_id',
    'accounting_month',
    'bank_balance',
    'other_accounts',
    'cash',
    'insurances',
    'investments',
    'debt_credit',
];

 protected $dates = ['accounting_month'];

}
