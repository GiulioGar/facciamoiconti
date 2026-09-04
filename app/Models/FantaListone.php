<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantaListone extends Model
{
    use HasFactory;

    protected $table = 'fanta_listone';

protected $fillable = [
    'external_id',
    'ruolo',
    'ruolo_esteso',
    'nome',
    'squadra',
    'fvm',
    'quota_a',
    'quota_i',
    'diff_quota',
    'quota_a_m',
    'quota_i_m',
    'diff_quota_m',
    'fvm_m',
    'score',
    'fanta_index',
    'ia',
    'ia_confidence',
    'ia_status',
    'ia_sources',
    'fanta_fascia',
    'stato',
    'like',
    'dislike',
    'titolare',
    'titolare_goat',
    'titolare_esperto2',
    'mv24',
    'level',
    'recommended_credits',
];

    protected $casts = [
        'level'               => 'integer',
        'recommended_credits' => 'integer',
        'score'               => 'decimal:2',
        'fanta_index'         => 'integer',
        'ia'                  => 'integer',
        'ia_confidence'       => 'integer',
        'ia_sources'          => 'integer',
        'fanta_fascia'        => 'integer',
        'titolare_goat'       => 'integer',
        'titolare_esperto2'   => 'integer',
    ];

}
