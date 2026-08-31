<?php

namespace App\Services\Fantacalcio;

class RosaBudgetCalculator
{
    /**
     * Calculate the budget available for open slots.
     *
     * The current base_perc values are kept as relative weights. The minimum
     * completion budget is reserved before distributing the strategic amount.
     */
    public function calculate(int $budgetTotal, array $slots, array $assignedByIndex): array
    {
        $spentTotal = 0;
        foreach ($assignedByIndex as $assigned) {
            $spentTotal += (int) ($assigned['costo'] ?? 0);
        }

        $remaining = max(0, $budgetTotal - $spentTotal);
        $openIndexes = [];
        $completionFloor = 0;

        foreach ($slots as $key => &$slot) {
            $index = (int) ($slot['index'] ?? $key);
            $slot['index'] = $index;
            $slot['min_cost'] = max(0, (int) ($slot['min_cost'] ?? 1));

            if (!isset($assignedByIndex[$index])) {
                $openIndexes[] = $index;
                $completionFloor += $slot['min_cost'];
            }
        }
        unset($slot);

        $strategicBudget = max(0, $remaining - $completionFloor);
        $extraByIndex = [];

        foreach ($openIndexes as $index) {
            $extraByIndex[$index] = 0;
        }

        if ($remaining >= $completionFloor && $strategicBudget > 0 && !empty($openIndexes)) {
            $weightTotal = 0.0;
            $rawExtraByIndex = [];

            foreach ($slots as $slot) {
                $index = (int) $slot['index'];
                if (!isset($extraByIndex[$index])) {
                    continue;
                }

                $weight = max(0.0, (float) ($slot['base_perc'] ?? 0));
                $weightTotal += $weight;
                $rawExtraByIndex[$index] = $weight;
            }

            if ($weightTotal > 0) {
                $fractions = [];
                $allocated = 0;

                foreach ($rawExtraByIndex as $index => $weight) {
                    $raw = $strategicBudget * ($weight / $weightTotal);
                    $whole = (int) floor($raw);
                    $extraByIndex[$index] = $whole;
                    $allocated += $whole;
                    $fractions[$index] = $raw - $whole;
                }

                $leftover = $strategicBudget - $allocated;
                usort($openIndexes, function ($left, $right) use ($fractions) {
                    $fractionCompare = ($fractions[$right] ?? 0) <=> ($fractions[$left] ?? 0);

                    return $fractionCompare !== 0 ? $fractionCompare : $left <=> $right;
                });

                for ($i = 0; $i < $leftover; $i++) {
                    $index = $openIndexes[$i % count($openIndexes)];
                    $extraByIndex[$index]++;
                }
            }
        }

        // If the budget cannot cover the floor, never suggest more than remains.
        $minimumRemainder = $remaining;
        foreach ($slots as &$slot) {
            $index = (int) $slot['index'];

            if (isset($assignedByIndex[$index])) {
                $slot['suggested'] = 0;
                continue;
            }

            $minimum = min($slot['min_cost'], $minimumRemainder);
            $minimumRemainder -= $minimum;
            $slot['suggested'] = (int) ($minimum + ($remaining >= $completionFloor ? ($extraByIndex[$index] ?? 0) : 0));
        }
        unset($slot);

        return [
            'slots' => $slots,
            'spent' => $spentTotal,
            'remaining' => $remaining,
            'completion_floor' => $completionFloor,
            'strategic_budget' => $strategicBudget,
        ];
    }
}
