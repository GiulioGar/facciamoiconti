<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantaQuotazione extends Model
{
    use HasFactory;

    protected $table = 'fanta_quotazione';

    protected $fillable = [
        'external_id',
        'ruolo',
        'ruolo_esteso',
        'nome',
        'squadra',
        'fvm',
    ];
}
