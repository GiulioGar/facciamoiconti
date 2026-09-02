<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantaPlayerStats extends Model
{
    protected $table = 'fanta_player_stats';

    protected $fillable = [
        'external_id',
        'season',
        'pv',
        'mv',
        'fm',
        'gf',
        'gs',
        'rp',
        'rc',
        'rplus',
        'rminus',
        'ass',
        'amm',
        'esp',
        'au',
    ];

    protected $casts = [
        'pv'     => 'integer',
        'mv'     => 'decimal:2',
        'fm'     => 'decimal:2',
        'gf'     => 'integer',
        'gs'     => 'integer',
        'rp'     => 'integer',
        'rc'     => 'integer',
        'rplus'  => 'integer',
        'rminus' => 'integer',
        'ass'    => 'integer',
        'amm'    => 'integer',
        'esp'    => 'integer',
        'au'     => 'integer',
    ];
}
