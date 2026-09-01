<?php

namespace Tests\Unit;

use App\Services\Fantacalcio\RosaBudgetCalculator;
use Tests\TestCase;

class RosaBudgetCalculatorTest extends TestCase
{
    public function test_empty_roster_reserves_the_completion_floor(): void
    {
        $result = $this->calculator()->calculate(100, $this->slots(8), []);

        $this->assertSame(0, $result['spent']);
        $this->assertSame(100, $result['remaining']);
        $this->assertSame(8, $result['completion_floor']);
        $this->assertSame(92, $result['strategic_budget']);
    }

    public function test_remaining_budget_of_100_with_eight_open_slots_has_floor_8(): void
    {
        $result = $this->calculator()->calculate(100, $this->slots(8), []);

        $this->assertSame(8, $result['completion_floor']);
        $this->assertSame(92, $result['strategic_budget']);
    }

    public function test_remaining_equal_to_floor_suggests_only_minimum_costs(): void
    {
        $result = $this->calculator()->calculate(8, $this->slots(8), []);

        $this->assertSame(8, $result['completion_floor']);
        $this->assertSame(0, $result['strategic_budget']);
        $this->assertSame(8, $this->suggestedTotal($result['slots']));
        $this->assertSame([1, 1, 1, 1, 1, 1, 1, 1], $this->suggestedValues($result['slots']));
    }

    public function test_remaining_below_floor_never_produces_negative_values(): void
    {
        $result = $this->calculator()->calculate(3, $this->slots(8), []);

        $this->assertSame(8, $result['completion_floor']);
        $this->assertSame(0, $result['strategic_budget']);
        $this->assertSame(3, $this->suggestedTotal($result['slots']));
        $this->assertContains(0, $this->suggestedValues($result['slots']));

        foreach ($result['slots'] as $slot) {
            $this->assertGreaterThanOrEqual(0, $slot['suggested']);
        }
    }

    public function test_occupied_slots_are_excluded_from_floor_and_redistribution(): void
    {
        $assigned = [
            0 => ['costo' => 20],
            3 => ['costo' => 10],
        ];

        $result = $this->calculator()->calculate(100, $this->slots(8), $assigned);

        $this->assertSame(30, $result['spent']);
        $this->assertSame(70, $result['remaining']);
        $this->assertSame(6, $result['completion_floor']);
        $this->assertSame(64, $result['strategic_budget']);
        $this->assertSame(0, $result['slots'][0]['suggested']);
        $this->assertSame(0, $result['slots'][3]['suggested']);
    }

    public function test_zero_cost_goalkeeper_cover_does_not_increase_spent_total(): void
    {
        $slots = [
            ['index' => 0, 'role_token' => 'P', 'base_perc' => 0.5, 'min_cost' => 1],
            ['index' => 1, 'role_token' => 'P', 'base_perc' => 0.5, 'min_cost' => 1],
        ];
        $assigned = [
            0 => ['costo' => 25],
            1 => ['costo' => 0],
        ];

        $result = $this->calculator()->calculate(100, $slots, $assigned);

        $this->assertSame(25, $result['spent']);
        $this->assertSame(75, $result['remaining']);
        $this->assertSame(0, $result['completion_floor']);
    }

    public function test_suggested_total_for_open_slots_does_not_exceed_remaining_budget(): void
    {
        $slots = $this->slots(8);
        $assigned = [0 => ['costo' => 95]];

        $result = $this->calculator()->calculate(100, $slots, $assigned);

        $this->assertLessThanOrEqual($result['remaining'], $this->suggestedTotal($result['slots']));
    }

    public function test_empty_goalkeeper_roster_has_target_25_and_two_uniform_block_allocations(): void
    {
        $result = $this->calculator()->calculate(500, $this->goalkeeperSlots(), []);
        $goalkeeper = $result['goalkeeper'];

        $this->assertSame(25.0, $goalkeeper['target']);
        $this->assertSame(0, $goalkeeper['blocks_owned']);
        $this->assertSame(2, $goalkeeper['blocks_to_open']);
        $this->assertSame(0, $goalkeeper['block_spent']);
        $this->assertSame(2, $goalkeeper['completion_floor']);
        $this->assertSame(23, $goalkeeper['strategic_budget']);
        $this->assertSame([13, 12], $goalkeeper['recommended_new_block_allocations']);
        $this->assertSame(12.5, $goalkeeper['recommended_new_block']);
    }

    public function test_existing_napoli_block_leaves_a_new_block_recommendation_of_five(): void
    {
        $assigned = [0 => ['team' => 'Napoli', 'classic_role' => 'P', 'costo' => 20]];

        $goalkeeper = $this->calculator()->calculate(500, $this->goalkeeperSlots(), $assigned)['goalkeeper'];

        $this->assertSame(1, $goalkeeper['blocks_owned']);
        $this->assertSame(1, $goalkeeper['blocks_to_open']);
        $this->assertSame(20, $goalkeeper['block_spent']);
        $this->assertSame(1, $goalkeeper['completion_floor']);
        $this->assertSame(4, $goalkeeper['strategic_budget']);
        $this->assertSame(5.0, $goalkeeper['recommended_new_block']);
    }

    public function test_two_existing_blocks_need_no_new_block(): void
    {
        $assigned = [
            0 => ['team' => 'Napoli', 'classic_role' => 'P', 'costo' => 20],
            1 => ['team' => 'Monza', 'classic_role' => 'P', 'costo' => 5],
        ];

        $goalkeeper = $this->calculator()->calculate(500, $this->goalkeeperSlots(), $assigned)['goalkeeper'];

        $this->assertSame(2, $goalkeeper['blocks_owned']);
        $this->assertSame(0, $goalkeeper['blocks_to_open']);
        $this->assertSame(25, $goalkeeper['block_spent']);
        $this->assertSame(0, $goalkeeper['strategic_budget']);
        $this->assertSame(0, $goalkeeper['recommended_new_block']);
    }

    public function test_goalkeeper_cover_has_zero_economic_recommendation(): void
    {
        $assigned = [
            0 => ['team' => 'Napoli', 'classic_role' => 'P', 'costo' => 20],
            1 => ['team' => 'Napoli', 'classic_role' => 'P', 'costo' => 0],
        ];

        $goalkeeper = $this->calculator()->calculate(500, $this->goalkeeperSlots(), $assigned)['goalkeeper'];

        $this->assertSame(1, $goalkeeper['blocks_owned']);
        $this->assertSame(0, $goalkeeper['recommended_cover']);
        $this->assertSame(20, $goalkeeper['block_spent']);
    }

    public function test_three_existing_blocks_above_target_receive_no_new_goalkeeper_budget(): void
    {
        $assigned = [
            0 => ['team' => 'Napoli', 'classic_role' => 'P', 'costo' => 10],
            1 => ['team' => 'Monza', 'classic_role' => 'P', 'costo' => 10],
            2 => ['team' => 'Lazio', 'classic_role' => 'P', 'costo' => 10],
        ];

        $goalkeeper = $this->calculator()->calculate(500, $this->goalkeeperSlots(), $assigned)['goalkeeper'];

        $this->assertSame(3, $goalkeeper['blocks_owned']);
        $this->assertSame(0, $goalkeeper['blocks_to_open']);
        $this->assertSame(0, $goalkeeper['strategic_budget']);
    }

    public function test_six_different_goalkeeper_teams_do_not_require_a_new_block(): void
    {
        $assigned = [];
        foreach (['Napoli', 'Monza', 'Lazio', 'Roma', 'Milan', 'Inter'] as $index => $team) {
            $assigned[$index] = ['team' => $team, 'classic_role' => 'P', 'costo' => 2];
        }

        $goalkeeper = $this->calculator()->calculate(500, $this->goalkeeperSlots(), $assigned)['goalkeeper'];

        $this->assertSame(6, $goalkeeper['blocks_owned']);
        $this->assertSame(0, $goalkeeper['blocks_to_open']);
        $this->assertSame(0, $goalkeeper['recommended_new_block']);
    }

    public function test_goalkeeper_team_names_are_normalized_for_block_counting(): void
    {
        $assigned = [
            0 => ['team' => ' Napoli ', 'classic_role' => 'P', 'costo' => 20],
            1 => ['team' => 'napoli', 'classic_role' => 'P', 'costo' => 0],
        ];

        $goalkeeper = $this->calculator()->calculate(500, $this->goalkeeperSlots(), $assigned)['goalkeeper'];

        $this->assertSame(1, $goalkeeper['blocks_owned']);
        $this->assertSame(20, $goalkeeper['block_spent']);
    }

    public function test_overspent_goalkeeper_department_has_no_negative_budget(): void
    {
        $assigned = [
            0 => ['team' => 'Napoli', 'classic_role' => 'P', 'costo' => 30],
        ];

        $goalkeeper = $this->calculator()->calculate(500, $this->goalkeeperSlots(), $assigned)['goalkeeper'];

        $this->assertSame(0, $goalkeeper['remaining_target']);
        $this->assertSame(0, $goalkeeper['strategic_budget']);
        $this->assertSame(0, $goalkeeper['recommended_new_block']);
    }

    public function test_empty_dca_roster_with_budget_500_has_targets_45_150_280(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), []);

        $this->assertSame(45, $result['roles']['D']['target']);
        $this->assertSame(150, $result['roles']['C']['target']);
        $this->assertSame(280, $result['roles']['A']['target']);
    }

    public function test_internal_slot_hierarchy_is_respected(): void
    {
        $slots = $this->dcaSlots();
        $result = $this->calculator()->calculate(500, $slots, []);
        $suggested = [];

        foreach ($result['slots'] as $slot) {
            $suggested[$slot['slot_code']] = $slot['suggested'];
        }

        $this->assertGreaterThan($suggested['D2'], $suggested['D1']);
        $this->assertGreaterThan($suggested['D5'], $suggested['D2']);
        $this->assertGreaterThan($suggested['D7'], $suggested['D5']);
        $this->assertGreaterThan($suggested['C2'], $suggested['C1']);
        $this->assertGreaterThan($suggested['C3'], $suggested['C2']);
        $this->assertGreaterThan($suggested['C4'], $suggested['C3']);
        $this->assertGreaterThan($suggested['C5'], $suggested['C4']);
        $this->assertGreaterThan($suggested['C6'], $suggested['C5']);
        $this->assertGreaterThan($suggested['A2'], $suggested['A1']);
        $this->assertGreaterThan($suggested['A3'], $suggested['A2']);
        $this->assertGreaterThan($suggested['A4'], $suggested['A3']);
        $this->assertGreaterThanOrEqual($suggested['A5'], $suggested['A4']);
        $this->assertGreaterThanOrEqual($suggested['A6'], $suggested['A4']);
    }

    public function test_empty_dca_roster_with_budget_500_has_expected_c_and_a_slot_suggestions(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), []);
        $suggested = [];

        foreach ($result['slots'] as $slot) {
            $suggested[$slot['slot_code']] = $slot['suggested'];
        }

        $this->assertSame(43, $suggested['C1']);
        $this->assertSame(33, $suggested['C2']);
        $this->assertSame(27, $suggested['C3']);
        $this->assertSame(17, $suggested['C4']);
        $this->assertSame(12, $suggested['C5']);
        $this->assertSame(6, $suggested['C6']);
        $this->assertSame(6, $suggested['C7']);
        $this->assertSame(6, $suggested['C8']);
        $this->assertSame(114, $suggested['A1']);
        $this->assertSame(86, $suggested['A2']);
        $this->assertSame(39, $suggested['A3']);
        $this->assertSame(20, $suggested['A4']);
        $this->assertSame(11, $suggested['A5']);
        $this->assertSame(10, $suggested['A6']);
    }

    public function test_empty_dca_roster_with_budget_500_has_expected_c_and_a_maximums(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), []);
        $slots = $this->slotsByCode($result['slots']);

        $this->assertSame(43, $slots['C1']['target']);
        $this->assertSame(56, $slots['C1']['massimo']);
        $this->assertSame(33, $slots['C2']['target']);
        $this->assertSame(42, $slots['C2']['massimo']);
        $this->assertSame(27, $slots['C3']['target']);
        $this->assertSame(33, $slots['C3']['massimo']);
        $this->assertSame(17, $slots['C4']['target']);
        $this->assertSame(20, $slots['C4']['massimo']);
        $this->assertSame(12, $slots['C5']['target']);
        $this->assertSame(14, $slots['C5']['massimo']);
        $this->assertSame(6, $slots['C6']['target']);
        $this->assertSame(7, $slots['C6']['massimo']);
        $this->assertSame(6, $slots['C7']['target']);
        $this->assertSame(7, $slots['C7']['massimo']);
        $this->assertSame(6, $slots['C8']['target']);
        $this->assertSame(7, $slots['C8']['massimo']);
        $this->assertSame(114, $slots['A1']['target']);
        $this->assertSame(143, $slots['A1']['massimo']);
        $this->assertSame(86, $slots['A2']['target']);
        $this->assertSame(104, $slots['A2']['massimo']);
        $this->assertSame(39, $slots['A3']['target']);
        $this->assertSame(45, $slots['A3']['massimo']);
        $this->assertSame(20, $slots['A4']['target']);
        $this->assertSame(22, $slots['A4']['massimo']);
        $this->assertSame(11, $slots['A5']['target']);
        $this->assertSame(12, $slots['A5']['massimo']);
        $this->assertSame(10, $slots['A6']['target']);
        $this->assertSame(11, $slots['A6']['massimo']);
    }

    public function test_a1_bought_at_130_recalculates_attack_targets_and_maximums(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 130],
        ]);
        $slots = $this->slotsByCode($result['slots']);

        $this->assertSame(370, $result['remaining']);
        $this->assertSame(150, $result['roles']['A']['remaining_target']);
        $this->assertSame(78, $slots['A2']['target']);
        $this->assertSame(90, $slots['A2']['massimo']);
        $this->assertSame(35, $slots['A3']['target']);
        $this->assertSame(40, $slots['A3']['massimo']);
        $this->assertSame(18, $slots['A4']['target']);
        $this->assertSame(20, $slots['A4']['massimo']);
        $this->assertSame(10, $slots['A5']['target']);
        $this->assertSame(11, $slots['A5']['massimo']);
        $this->assertSame(9, $slots['A6']['target']);
        $this->assertSame(10, $slots['A6']['massimo']);
    }

    public function test_a1_bought_at_145_recalculates_attack_targets_and_maximums(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 145],
        ]);
        $slots = $this->slotsByCode($result['slots']);

        $this->assertSame(355, $result['remaining']);
        $this->assertSame(135, $result['roles']['A']['remaining_target']);
        $this->assertSame(70, $slots['A2']['target']);
        $this->assertSame(81, $slots['A2']['massimo']);
        $this->assertSame(31, $slots['A3']['target']);
        $this->assertSame(35, $slots['A3']['massimo']);
        $this->assertSame(16, $slots['A4']['target']);
        $this->assertSame(18, $slots['A4']['massimo']);
        $this->assertSame(9, $slots['A5']['target']);
        $this->assertSame(10, $slots['A5']['massimo']);
        $this->assertSame(9, $slots['A6']['target']);
        $this->assertSame(10, $slots['A6']['massimo']);
    }

    public function test_buying_a1_below_target_keeps_attack_targets_and_maximums_consistent(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 95],
        ]);
        $slots = $this->slotsByCode($result['slots']);

        $this->assertSame(405, $result['remaining']);
        $this->assertSame(185, $result['roles']['A']['remaining_target']);
        $this->assertSame(96, $slots['A2']['target']);
        $this->assertSame(112, $slots['A2']['massimo']);
        $this->assertSame(43, $slots['A3']['target']);
        $this->assertSame(49, $slots['A3']['massimo']);
        $this->assertSame(22, $slots['A4']['target']);
        $this->assertSame(24, $slots['A4']['massimo']);
        $this->assertSame(12, $slots['A5']['target']);
        $this->assertSame(13, $slots['A5']['massimo']);
        $this->assertSame(12, $slots['A6']['target']);
        $this->assertSame(13, $slots['A6']['massimo']);
    }

    public function test_overspent_attack_department_collapses_maximum_to_target(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 300],
        ]);
        $slots = $this->slotsByCode($result['slots']);

        $this->assertSame(0, $result['roles']['A']['remaining_target']);
        $this->assertSame($slots['A2']['target'], $slots['A2']['massimo']);
        $this->assertSame($slots['A3']['target'], $slots['A3']['massimo']);
        $this->assertSame($slots['A4']['target'], $slots['A4']['massimo']);
        $this->assertSame($slots['A5']['target'], $slots['A5']['massimo']);
        $this->assertSame($slots['A6']['target'], $slots['A6']['massimo']);
    }

    public function test_hard_cap_lower_than_theoretical_maximum_is_enforced(): void
    {
        $result = $this->calculator()->calculate(140, $this->dcaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 90],
        ]);
        $slots = $this->slotsByCode($result['slots']);

        $this->assertSame(6, $slots['A2']['hard_cap']);
        $this->assertSame(6, $slots['A2']['massimo']);
        $this->assertSame(1, $slots['A6']['hard_cap']);
        $this->assertSame(1, $slots['A6']['massimo']);
    }

    public function test_maximum_never_exceeds_hard_cap(): void
    {
        $result = $this->calculator()->calculate(500, $this->allRosaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 145],
            14 => ['classic_role' => 'C', 'costo' => 55],
            0 => ['classic_role' => 'P', 'team' => 'Napoli', 'costo' => 20],
            1 => ['classic_role' => 'P', 'team' => 'Napoli', 'costo' => 0],
        ]);

        foreach ($result['slots'] as $slot) {
            if (($slot['role'] ?? null) === 'P' || (int) ($slot['suggested'] ?? 0) === 0) {
                continue;
            }

            $this->assertLessThanOrEqual($slot['hard_cap'], $slot['massimo']);
        }
    }

    public function test_role_under_target_receives_strategic_budget(): void
    {
        $assigned = [
            6 => ['classic_role' => 'D', 'costo' => 20],
        ];

        $result = $this->calculator()->calculate(500, $this->dcaSlots(), $assigned);

        $this->assertSame(25, $result['roles']['D']['remaining_target']);
        $this->assertGreaterThan(0, $result['roles']['D']['capacity']);
        $this->assertGreaterThan(0, $result['roles']['D']['strategic_budget']);
    }

    public function test_role_above_target_has_zero_capacity_and_budget(): void
    {
        $assigned = [
            6 => ['classic_role' => 'D', 'costo' => 50],
        ];

        $result = $this->calculator()->calculate(500, $this->dcaSlots(), $assigned);

        $this->assertSame(0, $result['roles']['D']['remaining_target']);
        $this->assertSame(0, $result['roles']['D']['capacity']);
        $this->assertSame(0, $result['roles']['D']['strategic_budget']);
    }

    public function test_closed_role_has_zero_capacity_and_surplus_goes_to_open_roles(): void
    {
        $slots = array_values(array_filter($this->dcaSlots(), function ($slot) {
            return $slot['role'] !== 'D';
        }));
        $assigned = [];
        foreach ($this->dcaSlots() as $slot) {
            if ($slot['role'] === 'D') {
                $assigned[$slot['index']] = ['classic_role' => 'D', 'costo' => 1];
            }
        }

        $result = $this->calculator()->calculate(500, $slots, $assigned);

        $this->assertSame(0, $result['roles']['D']['open_slots']);
        $this->assertSame(0, $result['roles']['D']['capacity']);
        $this->assertGreaterThan(0, $result['roles']['C']['strategic_budget']);
        $this->assertGreaterThan(0, $result['roles']['A']['strategic_budget']);
    }

    public function test_spending_below_target_reduces_the_remaining_target_only_by_actual_cost(): void
    {
        $empty = $this->calculator()->calculate(500, $this->dcaSlots(), []);
        $spent = $this->calculator()->calculate(500, $this->dcaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 20],
        ]);

        $this->assertSame(280, $empty['roles']['A']['remaining_target']);
        $this->assertSame(260, $spent['roles']['A']['remaining_target']);
        $emptyShare = $empty['roles']['D']['strategic_budget'] / $empty['strategic_budget_dca'];
        $spentShare = $spent['roles']['D']['strategic_budget'] / $spent['strategic_budget_dca'];
        $this->assertGreaterThan($emptyShare, $spentShare);
    }

    public function test_spending_above_target_removes_that_role_from_remaining_distribution(): void
    {
        $result = $this->calculator()->calculate(500, $this->dcaSlots(), [
            22 => ['classic_role' => 'A', 'costo' => 300],
        ]);

        $this->assertSame(0, $result['roles']['A']['remaining_target']);
        $this->assertSame(0, $result['roles']['A']['capacity']);
        $this->assertSame(0, $result['roles']['A']['strategic_budget']);
    }

    public function test_dca_suggestions_plus_completion_floor_do_not_exceed_remaining(): void
    {
        $result = $this->calculator()->calculate(500, $this->allRosaSlots(), []);
        $suggestedTotal = $this->suggestedTotal($result['slots']);

        $this->assertLessThanOrEqual($result['remaining'], $suggestedTotal + $result['completion_floor']);
    }

    public function test_goalkeeper_block_model_remains_separate_from_dca_distribution(): void
    {
        $assigned = [
            0 => ['classic_role' => 'P', 'team' => 'Napoli', 'costo' => 20],
            1 => ['classic_role' => 'P', 'team' => 'Napoli', 'costo' => 0],
        ];

        $result = $this->calculator()->calculate(500, $this->allRosaSlots(), $assigned);

        $this->assertSame(1, $result['goalkeeper']['blocks_owned']);
        $this->assertSame(0, $result['goalkeeper']['recommended_cover']);
        $this->assertSame(20, $result['goalkeeper']['block_spent']);
        $this->assertArrayHasKey('strategic_budget', $result['roles']['D']);
    }

    private function calculator(): RosaBudgetCalculator
    {
        return new RosaBudgetCalculator();
    }

    private function slots(int $count): array
    {
        $slots = [];
        for ($index = 0; $index < $count; $index++) {
            $slots[] = [
                'index' => $index,
                'role_token' => 'D',
                'base_perc' => 1 / $count,
                'min_cost' => 1,
            ];
        }

        return $slots;
    }

    private function goalkeeperSlots(): array
    {
        $slots = [];
        for ($index = 0; $index < 6; $index++) {
            $slots[] = [
                'index' => $index,
                'role_token' => 'P',
                'base_perc' => 0.001,
                'min_cost' => 1,
            ];
        }

        return $slots;
    }

    private function dcaSlots(): array
    {
        $slots = [];
        foreach (config('fantacalcio.rosa_dca_slots', []) as $roleSlots) {
            foreach ($roleSlots as $slot) {
                $slot['role_token'] = $slot['role'];
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    private function allRosaSlots(): array
    {
        return array_merge($this->goalkeeperSlots(), $this->dcaSlots());
    }

    private function suggestedTotal(array $slots): int
    {
        return array_sum(array_column($slots, 'suggested'));
    }

    private function suggestedValues(array $slots): array
    {
        return array_values(array_column($slots, 'suggested'));
    }

    private function slotsByCode(array $slots): array
    {
        $indexed = [];

        foreach ($slots as $slot) {
            $indexed[$slot['slot_code']] = $slot;
        }

        return $indexed;
    }
}
