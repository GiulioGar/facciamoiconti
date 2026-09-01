<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantaRosa extends Model
{
    use HasFactory;

    protected $table = 'fanta_rosa';

    protected $fillable = [
        'external_id',
        'ruolo_esteso',
        'nome',
        'squadra',
        'costo',
        'target_snapshot',
        'massimo_snapshot',
        'classic_role',
        'slot_index',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'costo' => 'integer',
        'target_snapshot' => 'integer',
        'massimo_snapshot' => 'integer',
        'slot_index' => 'integer',
    ];
}
