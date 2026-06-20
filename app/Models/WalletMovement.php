<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletMovement extends Model
{
    protected $fillable = [
        'user_id',
        'family_id',
        'account',
        'amount',
        'source_type',
        'source_id',
        'date',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}
