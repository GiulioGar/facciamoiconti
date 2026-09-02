<?php

namespace App\Services\Fantacalcio;

use App\Models\FantaListone;
use App\Models\FantaPlayerStats;

class AppetibilityCalculator
{
    /**
     * Formula C: computa score appetibilità per un gruppo di giocatori dello stesso ruolo.
     *
     * Ogni elemento di $players deve avere:
     *   external_id, fvm, like, dislike, pv (nullable), mv (nullable), fm (nullable)
     *
     * Returns [external_id => score (float 0-100)]
     */
    public function computeForRole(array $players): array
    {
        $fvms = array_map(fn($p) => (float)($p['fvm'] ?? 0), $players);

        // Giocatori con presenze e stats complete
        $withPv = array_values(array_filter($players, fn($p) =>
            (int)($p['pv'] ?? 0) > 0 &&
            isset($p['fm']) && $p['fm'] !== null &&
            isset($p['mv']) && $p['mv'] !== null
        ));
        $fmVals = array_map(fn($p) => (float)$p['fm'], $withPv);
        $mvVals = array_map(fn($p) => (float)$p['mv'], $withPv);
        $avgPerf = $this->roleAvgPerf($withPv, $fmVals, $mvVals);

        // Giocatori con fanta_index per calcolo percentile F
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
            $hasFm   = $pv > 0
                && isset($p['fm']) && $p['fm'] !== null
                && isset($p['mv']) && $p['mv'] !== null;
            $hasFi   = isset($p['fanta_index']) && $p['fanta_index'] !== null;

            // A: percentile FVM nel ruolo (peso 0.25)
            $A = $this->percentileRank($fvm, $fvms);

            // B: performance storica ponderata per affidabilità (peso 0.35)
            $rel = min(1.0, $pv / 20.0);
            if ($hasFm) {
                $fmPct   = $this->percentileRank((float)$p['fm'], $fmVals);
                $mvPct   = $this->percentileRank((float)$p['mv'], $mvVals);
                $perfRaw = 0.80 * $fmPct + 0.20 * $mvPct;
            } else {
                $perfRaw = $avgPerf;
                $rel     = 0.0;
            }
            $B = $rel * $perfRaw + (1.0 - $rel) * $avgPerf;

            // F: fanta_index percentile nel ruolo (peso 0.30); fallback 50 se assente
            $F = $hasFi
                ? $this->percentileRank((float)$p['fanta_index'], $fiVals)
                : 50.0;

            // C: correzione manuale like/dislike, clampata a ±10
            $net = $like - $dislike;
            $C   = $this->clamp($net / 10.0, -1.0, 1.0) * 10.0;

            $score           = $this->clamp(0.25 * $A + 0.35 * $B + 0.30 * $F + $C, 0.0, 100.0);
            $results[$extId] = round($score, 2);
        }

        return $results;
    }

    /**
     * Carica listone + stats da DB, calcola gli score, persiste in fanta_listone.score.
     * Restituisce il numero di righe aggiornate.
     */
    public function recalculate(?string $season = null): int
    {
        if ($season === null) {
            $season = $this->latestSeason();
        }

        $listone = FantaListone::select('external_id', 'ruolo', 'fvm', 'like', 'dislike', 'fanta_index')->get();
        $stats   = FantaPlayerStats::where('season', $season)
            ->select('external_id', 'pv', 'mv', 'fm')
            ->get()
            ->keyBy('external_id');

        $byRole = [];
        foreach ($listone as $player) {
            $st = $stats->get($player->external_id);
            $byRole[$player->ruolo][] = [
                'external_id' => $player->external_id,
                'fvm'         => $player->fvm,
                'like'        => $player->like ?? 0,
                'dislike'     => $player->dislike ?? 0,
                'pv'          => $st ? $st->pv : null,
                'mv'          => $st ? $st->mv : null,
                'fm'          => $st ? $st->fm : null,
                'fanta_index' => $player->fanta_index,
            ];
        }

        $updated = 0;
        foreach ($byRole as $players) {
            $scores = $this->computeForRole($players);
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

    private function roleAvgPerf(array $withPv, array $fmVals, array $mvVals): float
    {
        if (empty($withPv)) {
            return 50.0;
        }
        $sum = 0.0;
        foreach ($withPv as $p) {
            $sum += 0.80 * $this->percentileRank((float)$p['fm'], $fmVals)
                  + 0.20 * $this->percentileRank((float)$p['mv'], $mvVals);
        }
        return $sum / count($withPv);
    }

    private function latestSeason(): string
    {
        $row = FantaPlayerStats::selectRaw('MAX(season) as s')->first();
        return ($row && $row->s) ? $row->s : '2025-26';
    }
}
