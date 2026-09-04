<?php

namespace App\Services\Fantacalcio;

use App\Models\FantaListone;
use App\Models\FantaPlayerStats;

class AppetibilityCalculator
{
    // Pesi per componente B per ruolo: [peso, invertire_percentile]
    // P: punisce gol subiti/partita, premia rigori parati
    // D: premia gol+assist, penalizza ammonizioni
    // C: premia gol+assist con piu' peso ai gol
    // A: domina il gol/partita (peso 0.60)
    private $roleWeights = [
        'P' => [
            'mv'    => [0.40, false],
            'gs_pg' => [0.40, true],
            'rp'    => [0.20, false],
        ],
        'D' => [
            'mv'     => [0.40, false],
            'gf_pg'  => [0.25, false],
            'ass_pg' => [0.25, false],
            'amm_pg' => [0.10, true],
        ],
        'C' => [
            'mv'     => [0.30, false],
            'gf_pg'  => [0.35, false],
            'ass_pg' => [0.25, false],
            'amm_pg' => [0.10, true],
        ],
        'A' => [
            'mv'     => [0.20, false],
            'gf_pg'  => [0.60, false],
            'ass_pg' => [0.20, false],
        ],
    ];

    public function computeForRole(array $players, string $role = ''): array
    {
        $fvms   = array_map(fn($p) => (float)($p['fvm'] ?? 0), $players);

        $withPv = array_values(array_filter($players, fn($p) =>
            (int)($p['pv'] ?? 0) > 0 && isset($p['mv']) && $p['mv'] !== null
        ));

        $statArrays = $this->buildStatArrays($withPv, $role);
        $avgPerf    = $this->computeRoleAvgPerf($withPv, $role, $statArrays);

        $withFi = array_values(array_filter($players, fn($p) =>
            isset($p['fanta_index']) && $p['fanta_index'] !== null
        ));
        $fiVals = array_map(fn($p) => (float)$p['fanta_index'], $withFi);

        $results = [];
        foreach ($players as $p) {
            $extId   = $p['external_id'];
            $fvm     = (float)($p['fvm'] ?? 0);
            $like    = (int)($p['like'] ?? 0);
            $dislike = (int)($p['dislike'] ?? 0);
            $pv      = (int)($p['pv'] ?? 0);
            $hasStat = $pv > 0 && isset($p['mv']) && $p['mv'] !== null;
            $hasFi   = isset($p['fanta_index']) && $p['fanta_index'] !== null;

            // A: percentile FVM nel ruolo (peso 0.20)
            $A = $this->percentileRank($fvm, $fvms);

            // B: performance storica role-specific, smorzata da affidabilita' (pv/20)
            $rel = min(1.0, $pv / 20.0);
            if ($hasStat) {
                $perfRaw = $this->computePerfRaw($p, $role, $statArrays);
            } else {
                $perfRaw = $avgPerf;
                $rel     = 0.0;
            }
            $B = $rel * $perfRaw + (1.0 - $rel) * $avgPerf;

            // F: fanta_index percentile nel ruolo (peso 0.20); fallback 50 se assente
            $F = $hasFi ? $this->percentileRank((float)$p['fanta_index'], $fiVals) : 50.0;

            // E: Indice Appetibilita (IA) — malus 25 se assente (non citato da fonti editoriali)
            $E = isset($p['ia']) && $p['ia'] !== null ? (float)$p['ia'] : 25.0;

            // C: correzione manuale like/dislike, clampata a +/-10
            $net = $like - $dislike;
            $C   = $this->clamp($net / 10.0, -1.0, 1.0) * 10.0;

            $score           = $this->clamp(0.25 * $A + 0.35 * $B + 0.25 * $F + 0.15 * $E + $C, 0.0, 100.0);
            $results[$extId] = round($score, 2);
        }

        return $results;
    }

    public function recalculate(?string $season = null): int
    {
        if ($season === null) {
            $season = $this->latestSeason();
        }

        $listone = FantaListone::select('external_id', 'ruolo', 'fvm', 'like', 'dislike', 'fanta_index', 'ia')->get();
        $stats   = FantaPlayerStats::where('season', $season)
            ->select('external_id', 'pv', 'mv', 'fm', 'gf', 'gs', 'rp', 'ass', 'amm')
            ->get()
            ->keyBy('external_id');

        $byRole = [];
        foreach ($listone as $player) {
            $st = $stats->get($player->external_id);
            $byRole[$player->ruolo][] = [
                'external_id'  => $player->external_id,
                'fvm'          => $player->fvm,
                'like'         => $player->like ?? 0,
                'dislike'      => $player->dislike ?? 0,
                'fanta_index'  => $player->fanta_index,
                'ia'           => $player->ia,
                'pv'           => $st ? $st->pv : null,
                'mv'           => $st ? $st->mv : null,
                'fm'           => $st ? $st->fm : null,
                'gf'           => $st ? $st->gf : null,
                'gs'           => $st ? $st->gs : null,
                'rp'           => $st ? $st->rp : null,
                'ass'          => $st ? $st->ass : null,
                'amm'          => $st ? $st->amm : null,
            ];
        }

        $updated = 0;
        foreach ($byRole as $role => $players) {
            $scores = $this->computeForRole($players, $role);
            foreach ($scores as $extId => $score) {
                FantaListone::where('external_id', $extId)->update(['score' => $score]);
                $updated++;
            }
        }

        return $updated;
    }

    public function percentileRank(float $x, array $vals): float
    {
        if (empty($vals)) {
            return 50.0;
        }
        $below = 0;
        foreach ($vals as $v) {
            if ((float)$v <= $x) {
                $below++;
            }
        }
        return ($below / count($vals)) * 100.0;
    }

    public function clamp(float $v, float $min, float $max): float
    {
        return max($min, min($max, $v));
    }

    private function buildStatArrays(array $withPv, string $role): array
    {
        $weights = $this->roleWeights[$role] ?? [];
        $arrays  = [];
        foreach (array_keys($weights) as $stat) {
            $arrays[$stat] = [];
        }

        foreach ($withPv as $p) {
            $pv = max(1, (int)$p['pv']);
            foreach (array_keys($weights) as $stat) {
                switch ($stat) {
                    case 'mv':     $arrays[$stat][] = (float)($p['mv']  ?? 0); break;
                    case 'gf_pg':  $arrays[$stat][] = (float)($p['gf']  ?? 0) / $pv; break;
                    case 'ass_pg': $arrays[$stat][] = (float)($p['ass'] ?? 0) / $pv; break;
                    case 'gs_pg':  $arrays[$stat][] = (float)($p['gs']  ?? 0) / $pv; break;
                    case 'rp':     $arrays[$stat][] = (float)($p['rp']  ?? 0); break;
                    case 'amm_pg': $arrays[$stat][] = (float)($p['amm'] ?? 0) / $pv; break;
                }
            }
        }

        return $arrays;
    }

    private function computePerfRaw(array $p, string $role, array $statArrays): float
    {
        $weights = $this->roleWeights[$role] ?? [];
        if (empty($weights)) {
            return 50.0;
        }

        $pv   = max(1, (int)$p['pv']);
        $perf = 0.0;

        foreach ($weights as $stat => $config) {
            $weight = $config[0];
            $invert = $config[1];

            switch ($stat) {
                case 'mv':     $val = (float)($p['mv']  ?? 0); break;
                case 'gf_pg':  $val = (float)($p['gf']  ?? 0) / $pv; break;
                case 'ass_pg': $val = (float)($p['ass'] ?? 0) / $pv; break;
                case 'gs_pg':  $val = (float)($p['gs']  ?? 0) / $pv; break;
                case 'rp':     $val = (float)($p['rp']  ?? 0); break;
                case 'amm_pg': $val = (float)($p['amm'] ?? 0) / $pv; break;
                default:       $val = 0.0; break;
            }

            $arr  = $statArrays[$stat] ?? [];
            $pct  = $this->percentileRank($val, $arr);
            $perf += $weight * ($invert ? (100.0 - $pct) : $pct);
        }

        return $perf;
    }

    private function computeRoleAvgPerf(array $withPv, string $role, array $statArrays): float
    {
        if (empty($withPv)) {
            return 50.0;
        }
        $sum = 0.0;
        foreach ($withPv as $p) {
            $sum += $this->computePerfRaw($p, $role, $statArrays);
        }
        return $sum / count($withPv);
    }

    private function latestSeason(): string
    {
        $row = FantaPlayerStats::selectRaw('MAX(season) as s')->first();
        return ($row && $row->s) ? $row->s : '2025-26';
    }
}
