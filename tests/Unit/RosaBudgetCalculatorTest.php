<?php

namespace Tests\Unit;

use App\Services\Fantacalcio\RosaBudgetCalculator;
use PHPUnit\Framework\TestCase;

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

    private function suggestedTotal(array $slots): int
    {
        return array_sum(array_column($slots, 'suggested'));
    }

    private function suggestedValues(array $slots): array
    {
        return array_values(array_column($slots, 'suggested'));
    }
}
