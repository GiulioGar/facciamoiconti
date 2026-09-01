<?php

namespace App\Services\Fantacalcio;

class RosaBudgetCalculator
{
    public function calculate(int $budgetTotal, array $slots, array $assignedByIndex): array
    {
        $spentTotal = 0;
        foreach ($assignedByIndex as $assigned) {
            $spentTotal += (int) ($assigned['costo'] ?? 0);
        }

        $remaining = max(0, $budgetTotal - $spentTotal);
        $goalkeeperIndexes = [];
        $openDcaByRole = ['D' => [], 'C' => [], 'A' => []];
        $completionFloor = 0;

        foreach ($slots as $key => &$slot) {
            $index = (int) ($slot['index'] ?? $key);
            $slot['index'] = $index;
            $slot['role'] = $slot['role'] ?? ($slot['role_token'] ?? null);
            $slot['role_token'] = $slot['role_token'] ?? $slot['role'];
            $slot['min_cost'] = max(0, (int) ($slot['min_cost'] ?? 1));
            $slot['max_extra_percentage'] = max(0.0, (float) ($slot['max_extra_percentage'] ?? 0));
            $role = $slot['role'];

            if ($role === 'P') {
                $goalkeeperIndexes[] = $index;
            } elseif (isset($openDcaByRole[$role]) && !isset($assignedByIndex[$index])) {
                $openDcaByRole[$role][] = $index;
                $completionFloor += $slot['min_cost'];
            }
        }
        unset($slot);

        $goalkeeper = $this->calculateGoalkeeperBudget(
            $budgetTotal,
            $slots,
            $assignedByIndex,
            count(array_filter($goalkeeperIndexes, function ($index) use ($assignedByIndex) {
                return !isset($assignedByIndex[$index]);
            }))
        );
        $completionFloor += $goalkeeper['completion_floor'];
        $strategicBudget = max(0, $remaining - $completionFloor);

        $roles = $this->calculateDcaRoles($budgetTotal, $slots, $assignedByIndex, $openDcaByRole);
        $dcaCapacity = 0;
        foreach ($roles as $role) {
            $dcaCapacity += $role['capacity'];
        }

        $strategicBudgetDca = min($strategicBudget, $dcaCapacity);
        $roleAllocations = $this->allocateInteger(
            $strategicBudgetDca,
            array_map(function ($role) {
                return $role['capacity'];
            }, $roles)
        );

        $extraByIndex = [];
        foreach ($roles as $roleToken => $role) {
            $roles[$roleToken]['strategic_budget'] = $roleAllocations[$roleToken] ?? 0;
            $weights = [];

            foreach ($role['open_indexes'] as $index) {
                $slot = $this->findSlot($slots, $index);
                $weights[$index] = max(0.0, (float) ($slot['strategic_weight'] ?? 0));
                $extraByIndex[$index] = 0;
            }

            $slotAllocations = $this->allocateInteger(
                $roles[$roleToken]['strategic_budget'],
                $weights
            );
            foreach ($slotAllocations as $index => $amount) {
                $extraByIndex[$index] = $amount;
            }
        }

        $minimumRemainder = $remaining;
        foreach ($slots as &$slot) {
            $index = (int) $slot['index'];
            $role = $slot['role'];

            if (isset($assignedByIndex[$index])) {
                $slot['suggested'] = 0;
                $slot['target'] = 0;
                $slot['massimo'] = 0;
                $slot['hard_cap'] = 0;
                continue;
            }

            if ($role === 'P') {
                $slot['suggested'] = $goalkeeper['recommended_cover'];
                $slot['target'] = $slot['suggested'];
                $slot['massimo'] = $slot['suggested'];
                $slot['hard_cap'] = $slot['suggested'];
                continue;
            }

            $minimum = min($slot['min_cost'], $minimumRemainder);
            $minimumRemainder -= $minimum;
            $slot['suggested'] = (int) ($minimum + (
                $remaining >= $completionFloor ? ($extraByIndex[$index] ?? 0) : 0
            ));
        }
        unset($slot);

        foreach ($slots as &$slot) {
            $index = (int) $slot['index'];
            $role = $slot['role'];

            if ($role === 'P' || isset($assignedByIndex[$index])) {
                continue;
            }

            $slot['target'] = (int) ($slot['suggested'] ?? 0);
            $slot['hard_cap'] = $this->calculateHardCap($slot, $remaining, $completionFloor);
            $slot['massimo'] = $this->calculateMaximum(
                $slot,
                $roles[$role] ?? ['target' => 0, 'remaining_target' => 0],
                $slot['hard_cap']
            );
        }
        unset($slot);

        return [
            'slots' => $slots,
            'spent' => $spentTotal,
            'remaining' => $remaining,
            'completion_floor' => $completionFloor,
            'strategic_budget' => $strategicBudget,
            'strategic_budget_dca' => $strategicBudgetDca,
            'goalkeeper' => $goalkeeper,
            'roles' => $roles,
        ];
    }

    private function calculateDcaRoles(
        int $budgetTotal,
        array $slots,
        array $assignedByIndex,
        array $openDcaByRole
    ): array {
        $percentages = config('fantacalcio.role_percentages', []);
        $roles = [];

        foreach (['D', 'C', 'A'] as $roleToken) {
            $target = (int) round($budgetTotal * (float) ($percentages[$roleToken] ?? 0));
            $spent = 0;

            foreach ($assignedByIndex as $index => $assigned) {
                $slot = $this->findSlot($slots, (int) $index);
                $role = $slot['role'] ?? ($assigned['classic_role'] ?? null);
                if ($role === $roleToken) {
                    $spent += (int) ($assigned['costo'] ?? 0);
                }
            }

            $floor = 0;
            foreach ($openDcaByRole[$roleToken] as $index) {
                $floor += $this->findSlot($slots, $index)['min_cost'];
            }

            $remainingTarget = max(0, $target - $spent);
            $capacity = empty($openDcaByRole[$roleToken])
                ? 0
                : max(0, $remainingTarget - $floor);

            $roles[$roleToken] = [
                'target' => $target,
                'spent' => $spent,
                'open_slots' => count($openDcaByRole[$roleToken]),
                'floor' => $floor,
                'remaining_target' => $remainingTarget,
                'capacity' => $capacity,
                'strategic_budget' => 0,
                'open_indexes' => $openDcaByRole[$roleToken],
            ];
        }

        return $roles;
    }

    private function calculateGoalkeeperBudget(
        int $budgetTotal,
        array $slots,
        array $assignedByIndex,
        int $emptySlotCount
    ): array {
        $config = config('fantacalcio.goalkeeper_economy', []);
        $targetPercentage = (float) ($config['target_percentage'] ?? 0.05);
        $targetBlocks = max(0, (int) ($config['target_blocks'] ?? 0));
        $minBlockCost = max(0, (int) ($config['min_block_cost'] ?? 0));
        $coverCost = max(0, (int) ($config['cover_cost'] ?? 0));
        $teams = [];

        foreach ($assignedByIndex as $index => $assigned) {
            $slot = $this->findSlot($slots, (int) $index);
            $role = $slot['role'] ?? ($assigned['classic_role'] ?? null);
            if ($role !== 'P') {
                continue;
            }

            $teamKey = $this->normalizeTeam($assigned['team'] ?? $assigned['squadra'] ?? '');
            if ($teamKey === '') {
                continue;
            }

            $teams[$teamKey] = ($teams[$teamKey] ?? 0) + (int) ($assigned['costo'] ?? 0);
        }

        $blocksOwned = count($teams);
        $blocksToOpen = min(max(0, $targetBlocks - $blocksOwned), $emptySlotCount);
        $target = (int) round($budgetTotal * $targetPercentage);
        $blockSpent = array_sum($teams);
        $remainingTarget = max(0, $target - $blockSpent);
        $completionFloor = $blocksToOpen * $minBlockCost;
        $strategicBudget = max(0, $remainingTarget - $completionFloor);
        $recommendedNewBlock = $blocksToOpen > 0
            ? $minBlockCost + ($strategicBudget / $blocksToOpen)
            : 0;

        return [
            'target' => $target,
            'blocks_owned' => $blocksOwned,
            'blocks_to_open' => $blocksToOpen,
            'block_spent' => $blockSpent,
            'remaining_target' => $remainingTarget,
            'completion_floor' => $completionFloor,
            'strategic_budget' => $strategicBudget,
            'recommended_new_block' => $recommendedNewBlock,
            'recommended_new_block_allocations' => $this->splitBudget(
                $strategicBudget + ($blocksToOpen * $minBlockCost),
                $blocksToOpen
            ),
            'recommended_cover' => $coverCost,
        ];
    }

    private function allocateInteger(int $budget, array $weights): array
    {
        if ($budget <= 0 || empty($weights)) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $allocations = [];
        $fractions = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $raw = $budget * ((float) $weight / $weightTotal);
            $whole = (int) floor($raw);
            $allocations[$key] = $whole;
            $fractions[$key] = $raw - $whole;
            $allocated += $whole;
        }

        uksort($fractions, function ($left, $right) use ($fractions) {
            $fractionCompare = ($fractions[$right] <=> $fractions[$left]);

            return $fractionCompare !== 0 ? $fractionCompare : ((string) $left <=> (string) $right);
        });

        $leftover = $budget - $allocated;
        $keys = array_keys($fractions);
        for ($i = 0; $i < $leftover; $i++) {
            $allocations[$keys[$i % count($keys)]]++;
        }

        return $allocations;
    }

    private function calculateHardCap(array $slot, int $remaining, int $completionFloor): int
    {
        $minimum = max(0, (int) ($slot['min_cost'] ?? 0));
        $otherFloor = max(0, $completionFloor - $minimum);

        return max($minimum, $remaining - $otherFloor);
    }

    private function calculateMaximum(array $slot, array $role, int $hardCap): int
    {
        $target = max(0, (int) ($slot['target'] ?? 0));
        if ($target <= 0) {
            return min($hardCap, $target);
        }

        $roleTarget = max(0, (int) ($role['target'] ?? 0));
        $remainingTarget = max(0, (int) ($role['remaining_target'] ?? 0));
        $gRaw = $roleTarget > 0
            ? min(1, max(0, $remainingTarget / $roleTarget))
            : 0;
        $gSoft = 0.5 + (0.5 * $gRaw);
        $targetBase = max(1, $target);
        $a = min(1, max(0, ($hardCap - $target) / $targetBase));
        $maxExtraPercentage = max(0.0, (float) ($slot['max_extra_percentage'] ?? 0));
        $theoretical = $target + (int) ceil($target * $maxExtraPercentage * $gSoft * $a);

        return min($hardCap, $theoretical);
    }

    private function findSlot(array $slots, int $index): array
    {
        foreach ($slots as $slot) {
            if ((int) ($slot['index'] ?? -1) === $index) {
                return $slot;
            }
        }

        return [];
    }

    private function normalizeTeam($team): string
    {
        $team = preg_replace('/\s+/u', ' ', trim((string) $team));

        return mb_strtoupper($team, 'UTF-8');
    }

    private function splitBudget(int $budget, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $base = intdiv($budget, $parts);
        $remainder = $budget % $parts;
        $allocations = array_fill(0, $parts, $base);

        for ($i = 0; $i < $remainder; $i++) {
            $allocations[$i]++;
        }

        return $allocations;
    }
}
