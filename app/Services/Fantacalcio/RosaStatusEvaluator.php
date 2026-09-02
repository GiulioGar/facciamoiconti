<?php

namespace App\Services\Fantacalcio;

class RosaStatusEvaluator
{
    public const OPPORTUNISTICO    = 'OPPORTUNISTICO';
    public const IN_PIANO          = 'IN_PIANO';
    public const AGGRESSIVO        = 'AGGRESSIVO';
    public const COMPRESSO         = 'COMPRESSO';
    public const RISCHIO_STRUTTURA = 'RISCHIO_STRUTTURA';

    private const SEVERITY = [
        self::OPPORTUNISTICO    => -1,
        self::IN_PIANO          =>  0,
        self::AGGRESSIVO        =>  1,
        self::COMPRESSO         =>  2,
        self::RISCHIO_STRUTTURA =>  3,
    ];

    private const SCORE = [
        self::OPPORTUNISTICO    => -1,
        self::IN_PIANO          =>  0,
        self::AGGRESSIVO        =>  1,
        self::COMPRESSO         =>  2,
        self::RISCHIO_STRUTTURA =>  3,
    ];

    /**
     * @param int   $budgetTotal     Same budget used for the current plan.
     * @param array $allSlots        Enriched slots from RosaBudgetCalculator::calculate() output.
     * @param array $currentPlan     Full output of RosaBudgetCalculator::calculate().
     * @param array $assignedByIndex Keyed by slot_index; each entry needs 'costo' and 'target_snapshot'.
     */
    public function evaluate(
        int $budgetTotal,
        array $allSlots,
        array $currentPlan,
        array $assignedByIndex
    ): array {
        $pristinePlan   = app(RosaBudgetCalculator::class)->calculate($budgetTotal, $allSlots, []);
        $pristineByCode = $this->indexByCode($pristinePlan['slots']);
        $currentByCode  = $this->indexByCode($currentPlan['slots']);

        $thresholds = config('fantacalcio.status_thresholds', []);
        $weights    = config('fantacalcio.status_weights', ['A' => 3, 'C' => 2, 'D' => 1]);
        $keySlots   = config('fantacalcio.key_slots', []);

        $roles = [];
        foreach (['D', 'C', 'A'] as $role) {
            $roles[$role] = $this->evaluateRole(
                $role,
                $allSlots,
                $currentByCode,
                $pristineByCode,
                $assignedByIndex,
                $thresholds,
                $keySlots[$role] ?? []
            );
        }

        return [
            'global_status' => $this->evaluateGlobal($roles, $weights),
            'roles'         => $roles,
            'goalkeeper'    => $this->evaluateGoalkeeper($currentPlan['goalkeeper'], $thresholds),
        ];
    }

    // -------------------------------------------------------------------------
    // Per-role evaluation
    // -------------------------------------------------------------------------

    private function evaluateRole(
        string $role,
        array $allSlots,
        array $currentByCode,
        array $pristineByCode,
        array $assignedByIndex,
        array $thresholds,
        array $keySlotCodes
    ): array {
        if (!$this->roleHasPurchases($role, $allSlots, $assignedByIndex)) {
            return [
                'status'               => null,
                'purchase_delta'       => null,
                'future_compression'   => null,
                'worst_key_slot_ratio' => null,
            ];
        }

        $pd = $this->computePurchaseDelta($role, $allSlots, $assignedByIndex);
        $fc = $this->computeFutureCompression($role, $allSlots, $currentByCode, $pristineByCode, $assignedByIndex);
        $wk = $this->computeWorstKeySlotRatio($allSlots, $currentByCode, $pristineByCode, $assignedByIndex, $keySlotCodes);

        return [
            'status'               => $this->resolveStatus($pd, $fc, $wk, $thresholds),
            'purchase_delta'       => $pd,
            'future_compression'   => $fc,
            'worst_key_slot_ratio' => $wk,
        ];
    }

    private function roleHasPurchases(string $role, array $allSlots, array $assignedByIndex): bool
    {
        foreach ($allSlots as $slot) {
            if (($slot['role'] ?? null) === $role && isset($assignedByIndex[(int) $slot['index']])) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Indicator: purchase_delta
    // -------------------------------------------------------------------------

    private function computePurchaseDelta(string $role, array $allSlots, array $assignedByIndex): ?float
    {
        $deltaSum     = 0;
        $plannedSpent = 0;

        foreach ($allSlots as $slot) {
            if (($slot['role'] ?? null) !== $role) {
                continue;
            }

            $index = (int) $slot['index'];
            if (!isset($assignedByIndex[$index])) {
                continue;
            }

            $snapshot = $assignedByIndex[$index]['target_snapshot'] ?? null;
            if ($snapshot === null) {
                continue;
            }

            $snapshot = (int) $snapshot;
            if ($snapshot <= 0) {
                continue;
            }

            $deltaSum     += (int) ($assignedByIndex[$index]['costo'] ?? 0) - $snapshot;
            $plannedSpent += $snapshot;
        }

        if ($plannedSpent <= 0) {
            return null;
        }

        return $deltaSum / $plannedSpent;
    }

    // -------------------------------------------------------------------------
    // Indicator: future_compression
    // -------------------------------------------------------------------------

    private function computeFutureCompression(
        string $role,
        array $allSlots,
        array $currentByCode,
        array $pristineByCode,
        array $assignedByIndex
    ): ?float {
        $weightedCurrent  = 0.0;
        $weightedPristine = 0.0;
        $hasOpen = false;

        foreach ($allSlots as $slot) {
            if (($slot['role'] ?? null) !== $role) {
                continue;
            }

            $index = (int) $slot['index'];
            if (isset($assignedByIndex[$index])) {
                continue; // closed slot — not in the future
            }

            $code    = $slot['slot_code'] ?? null;
            $weight  = (float) ($slot['strategic_weight'] ?? 0);
            $current = (float) ($currentByCode[$code]['target'] ?? 0);
            $pristine = (float) ($pristineByCode[$code]['target'] ?? 0);

            if (!$code || $pristine <= 0.0) {
                continue;
            }

            $weightedCurrent  += $current  * $weight;
            $weightedPristine += $pristine * $weight;
            $hasOpen = true;
        }

        if (!$hasOpen || $weightedPristine <= 0.0) {
            return null;
        }

        return 1.0 - ($weightedCurrent / $weightedPristine);
    }

    // -------------------------------------------------------------------------
    // Indicator: worst_key_slot_ratio
    // -------------------------------------------------------------------------

    private function computeWorstKeySlotRatio(
        array $allSlots,
        array $currentByCode,
        array $pristineByCode,
        array $assignedByIndex,
        array $keySlotCodes
    ): ?float {
        if (empty($keySlotCodes)) {
            return null;
        }

        $worst = null;

        foreach ($allSlots as $slot) {
            $code = $slot['slot_code'] ?? null;
            if (!$code || !in_array($code, $keySlotCodes, true)) {
                continue;
            }

            $index = (int) $slot['index'];
            if (isset($assignedByIndex[$index])) {
                continue; // closed — only care about future key slots
            }

            $pristine = (float) ($pristineByCode[$code]['target'] ?? 0);
            if ($pristine <= 0.0) {
                continue;
            }

            $current = (float) ($currentByCode[$code]['target'] ?? 0);
            $ratio   = $current / $pristine;

            if ($worst === null || $ratio < $worst) {
                $worst = $ratio;
            }
        }

        return $worst;
    }

    // -------------------------------------------------------------------------
    // Status resolution
    // -------------------------------------------------------------------------

    private function resolveStatus(
        ?float $pd,
        ?float $fc,
        ?float $wk,
        array $thresholds
    ): ?string {
        // Both indicators unavailable → no evaluation
        if ($pd === null && $fc === null) {
            return null;
        }

        $pdState = $pd !== null ? $this->stateFromPd($pd, $thresholds) : null;
        $fcState = $fc !== null ? $this->stateFromFc($fc, $thresholds) : null;
        $wkState = $wk !== null ? $this->stateFromWk($wk, $thresholds) : null;

        // Hard trigger from worst key slot
        if ($wkState === self::RISCHIO_STRUTTURA) {
            return self::RISCHIO_STRUTTURA;
        }

        // Merge the two primary indicators (take the more severe)
        $candidate = $this->mergeStates($pdState, $fcState);

        // Apply wk floor (COMPRESSO minimum) if triggered
        if ($wkState !== null) {
            $candidate = $this->maxSeverity($candidate, $wkState);
        }

        // OPPORTUNISTICO only if both available indicators agree
        if ($candidate === self::OPPORTUNISTICO) {
            if ($pdState !== null && $pdState !== self::OPPORTUNISTICO) {
                return self::IN_PIANO;
            }
            if ($fcState !== null && $fcState !== self::OPPORTUNISTICO) {
                return self::IN_PIANO;
            }
        }

        return $candidate;
    }

    private function stateFromPd(float $pd, array $thresholds): string
    {
        $t = $thresholds['purchase_delta'] ?? [];
        if ($pd < (float) ($t['opportunistic_max'] ?? -0.05)) return self::OPPORTUNISTICO;
        if ($pd <= (float) ($t['in_piano_max']      ??  0.12)) return self::IN_PIANO;
        if ($pd <= (float) ($t['aggressivo_max']    ??  0.28)) return self::AGGRESSIVO;
        if ($pd <= (float) ($t['compresso_max']     ??  0.48)) return self::COMPRESSO;
        return self::RISCHIO_STRUTTURA;
    }

    private function stateFromFc(float $fc, array $thresholds): string
    {
        $t = $thresholds['future_compression'] ?? [];
        if ($fc < (float) ($t['opportunistic_max'] ?? 0.00)) return self::OPPORTUNISTICO;
        if ($fc <= (float) ($t['in_piano_max']      ?? 0.10)) return self::IN_PIANO;
        if ($fc <= (float) ($t['aggressivo_max']    ?? 0.22)) return self::AGGRESSIVO;
        if ($fc <= (float) ($t['compresso_max']     ?? 0.38)) return self::COMPRESSO;
        return self::RISCHIO_STRUTTURA;
    }

    private function stateFromWk(float $wk, array $thresholds): ?string
    {
        $t = $thresholds['worst_key_slot'] ?? [];
        $noOverride   = (float) ($t['no_override_min'] ?? 0.75);
        $compressoMin = (float) ($t['compresso_min']   ?? 0.60);

        if ($wk >= $noOverride)   return null;
        if ($wk >= $compressoMin) return self::COMPRESSO;
        return self::RISCHIO_STRUTTURA;
    }

    private function mergeStates(?string $a, ?string $b): string
    {
        if ($a === null) return $b ?? self::IN_PIANO;
        if ($b === null) return $a;
        return $this->maxSeverity($a, $b);
    }

    private function maxSeverity(string $a, string $b): string
    {
        return (self::SEVERITY[$a] ?? 0) >= (self::SEVERITY[$b] ?? 0) ? $a : $b;
    }

    // -------------------------------------------------------------------------
    // Global status
    // -------------------------------------------------------------------------

    private function evaluateGlobal(array $roleResults, array $weights): ?string
    {
        // Hard rules: A or C in RISCHIO_STRUTTURA → global is RISCHIO_STRUTTURA
        foreach (['A', 'C'] as $heavyRole) {
            if (($roleResults[$heavyRole]['status'] ?? null) === self::RISCHIO_STRUTTURA) {
                return self::RISCHIO_STRUTTURA;
            }
        }

        $weightedScore = 0.0;
        $totalWeight   = 0;
        $anyEvaluated  = false;
        $allOpportunistic = true;

        foreach (['D', 'C', 'A'] as $role) {
            $status = $roleResults[$role]['status'] ?? null;
            if ($status === null) {
                continue;
            }

            $w = (int) ($weights[$role] ?? 1);
            $weightedScore += (self::SCORE[$status] ?? 0) * $w;
            $totalWeight   += $w;
            $anyEvaluated   = true;

            if ($status !== self::OPPORTUNISTICO) {
                $allOpportunistic = false;
            }
        }

        if (!$anyEvaluated) {
            return null;
        }

        // Hard rule: D in RISCHIO_STRUTTURA → global at least COMPRESSO
        if (($roleResults['D']['status'] ?? null) === self::RISCHIO_STRUTTURA) {
            $globalFromScore = $this->scoreToState($weightedScore / $totalWeight);
            return $this->maxSeverity($globalFromScore, self::COMPRESSO);
        }

        if ($allOpportunistic) {
            return self::OPPORTUNISTICO;
        }

        return $this->scoreToState($weightedScore / $totalWeight);
    }

    private function scoreToState(float $score): string
    {
        if ($score < -0.4) return self::OPPORTUNISTICO;
        if ($score <= 0.4) return self::IN_PIANO;
        if ($score <= 1.4) return self::AGGRESSIVO;
        if ($score <= 2.4) return self::COMPRESSO;
        return self::RISCHIO_STRUTTURA;
    }

    // -------------------------------------------------------------------------
    // Goalkeeper
    // -------------------------------------------------------------------------

    private function evaluateGoalkeeper(array $goalkeeper, array $thresholds): array
    {
        $blockSpent = (int) ($goalkeeper['block_spent'] ?? 0);
        $target     = (int) ($goalkeeper['target']      ?? 0);

        if ($target <= 0) {
            return ['status' => self::IN_PIANO, 'ratio' => null];
        }

        $ratio = $blockSpent / $target;
        $t     = $thresholds['goalkeeper'] ?? [];
        $oppMax = (float) ($t['opportunistic_max'] ?? 0.80);
        $ipMax  = (float) ($t['in_piano_max']      ?? 1.20);

        if ($ratio < $oppMax)  $status = self::OPPORTUNISTICO;
        elseif ($ratio <= $ipMax) $status = self::IN_PIANO;
        else   $status = self::AGGRESSIVO;

        return ['status' => $status, 'ratio' => $ratio];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function indexByCode(array $slots): array
    {
        $result = [];
        foreach ($slots as $slot) {
            $code = $slot['slot_code'] ?? null;
            if ($code !== null) {
                $result[$code] = $slot;
            }
        }
        return $result;
    }
}
