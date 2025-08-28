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
             'classic_role',   // ✅ AGGIUNTO
        'slot_index',     // ✅ (vedi Bug 2)
    ];

    protected $casts = [
        'external_id' => 'integer',
        'costo'       => 'integer',
         'slot_index'  => 'integer', // ✅
    ];
}
