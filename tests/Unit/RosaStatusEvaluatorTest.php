<?php

namespace Tests\Unit;

use App\Services\Fantacalcio\RosaBudgetCalculator;
use App\Services\Fantacalcio\RosaStatusEvaluator;
use Tests\TestCase;

class RosaStatusEvaluatorTest extends TestCase
{
    private RosaStatusEvaluator $evaluator;
    private RosaBudgetCalculator $calculator;
    private int $budget = 2000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator  = app(RosaStatusEvaluator::class);
        $this->calculator = app(RosaBudgetCalculator::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildSlots(): array
    {
        $gkSlots = array_map(function (array $slot) {
            return array_merge($slot, ['role_token' => 'P', 'role' => 'P']);
        }, config('fantacalcio.rosa_goalkeeper_slots', []));

        $dcaSlots = [];
        foreach (config('fantacalcio.rosa_dca_slots', []) as $roleSlots) {
            foreach ($roleSlots as $slot) {
                $dcaSlots[] = array_merge($slot, ['role_token' => $slot['role']]);
            }
        }

        return array_merge($gkSlots, $dcaSlots);
    }

    /**
     * Returns the current plan after running the calculator with the given
     * assignments, and also returns the enriched slots array.
     */
    private function makePlan(array $assignedByIndex): array
    {
        $slots = $this->buildSlots();
        $plan  = $this->calculator->calculate($this->budget, $slots, $assignedByIndex);
        return $plan;
    }

    /**
     * Returns the pristine target for a slot_code (rosa vuota).
     */
    private function pristineTarget(string $slotCode): int
    {
        $slots  = $this->buildSlots();
        $plan   = $this->calculator->calculate($this->budget, $slots, []);
        foreach ($plan['slots'] as $slot) {
            if (($slot['slot_code'] ?? null) === $slotCode) {
                return (int) $slot['target'];
            }
        }
        return 0;
    }

    /**
     * Returns the target that the planner shows for $slotCode when $assigned
     * slots are already bought. Used to simulate what target_snapshot stores.
     */
    private function snapshotFor(string $slotCode, array $assignedByIndex): int
    {
        $plan = $this->makePlan($assignedByIndex);
        foreach ($plan['slots'] as $slot) {
            if (($slot['slot_code'] ?? null) === $slotCode) {
                return (int) $slot['target'];
            }
        }
        return 0;
    }

    private function evaluate(array $assignedByIndex): array
    {
        $plan = $this->makePlan($assignedByIndex);
        return $this->evaluator->evaluate($this->budget, $plan['slots'], $plan, $assignedByIndex);
    }

    private function roleStatus(array $result, string $role): ?string
    {
        return $result['roles'][$role]['status'] ?? null;
    }

    // =========================================================================
    // ATTACCO
    // =========================================================================

    public function test_attacco_a1_450_is_in_piano(): void
    {
        // A1 slot index = 22, pristine_snapshot = 462
        $snapshot = $this->pristineTarget('A1'); // should be 462
        $assigned = [22 => ['costo' => 450, 'target_snapshot' => $snapshot, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::IN_PIANO, $this->roleStatus($result, 'A'));
    }

    public function test_attacco_a1_550_is_aggressivo(): void
    {
        $snapshot = $this->pristineTarget('A1');
        $assigned = [22 => ['costo' => 550, 'target_snapshot' => $snapshot, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::AGGRESSIVO, $this->roleStatus($result, 'A'));
    }

    public function test_attacco_a1_650_is_compresso(): void
    {
        $snapshot = $this->pristineTarget('A1');
        $assigned = [22 => ['costo' => 650, 'target_snapshot' => $snapshot, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::COMPRESSO, $this->roleStatus($result, 'A'));
    }

    public function test_attacco_a1_750_is_rischio_struttura(): void
    {
        $snapshot = $this->pristineTarget('A1');
        $assigned = [22 => ['costo' => 750, 'target_snapshot' => $snapshot, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::RISCHIO_STRUTTURA, $this->roleStatus($result, 'A'));
    }

    public function test_attacco_a1_450_a2_360_is_in_piano(): void
    {
        $snapA1 = $this->pristineTarget('A1'); // 462
        // A2 snapshot = target shown for A2 AFTER A1=450 is assigned
        $snapA2 = $this->snapshotFor('A2', [22 => ['costo' => 450, 'target_snapshot' => $snapA1, 'classic_role' => 'A']]);

        $assigned = [
            22 => ['costo' => 450, 'target_snapshot' => $snapA1, 'classic_role' => 'A'],
            23 => ['costo' => 360, 'target_snapshot' => $snapA2, 'classic_role' => 'A'],
        ];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::IN_PIANO, $this->roleStatus($result, 'A'));
    }

    public function test_attacco_a1_500_a2_350_is_aggressivo(): void
    {
        $snapA1 = $this->pristineTarget('A1'); // 462
        $snapA2 = $this->snapshotFor('A2', [22 => ['costo' => 500, 'target_snapshot' => $snapA1, 'classic_role' => 'A']]);

        $assigned = [
            22 => ['costo' => 500, 'target_snapshot' => $snapA1, 'classic_role' => 'A'],
            23 => ['costo' => 350, 'target_snapshot' => $snapA2, 'classic_role' => 'A'],
        ];

        $result = $this->evaluate($assigned);

        // purchase_delta is IN_PIANO (+7.7%) but future_compression elevates to AGGRESSIVO (+13.3%)
        $this->assertSame(RosaStatusEvaluator::AGGRESSIVO, $this->roleStatus($result, 'A'));
    }

    // =========================================================================
    // CENTROCAMPO
    // =========================================================================

    public function test_centrocampo_c1_180_is_opportunistico(): void
    {
        $snapshot = $this->pristineTarget('C1'); // 222
        $assigned = [14 => ['costo' => 180, 'target_snapshot' => $snapshot, 'classic_role' => 'C']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::OPPORTUNISTICO, $this->roleStatus($result, 'C'));
    }

    public function test_centrocampo_c1_222_is_in_piano(): void
    {
        $snapshot = $this->pristineTarget('C1');
        $assigned = [14 => ['costo' => 222, 'target_snapshot' => $snapshot, 'classic_role' => 'C']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::IN_PIANO, $this->roleStatus($result, 'C'));
    }

    public function test_centrocampo_c1_256_is_aggressivo(): void
    {
        $snapshot = $this->pristineTarget('C1');
        $assigned = [14 => ['costo' => 256, 'target_snapshot' => $snapshot, 'classic_role' => 'C']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::AGGRESSIVO, $this->roleStatus($result, 'C'));
    }

    public function test_centrocampo_c1_300_is_compresso(): void
    {
        $snapshot = $this->pristineTarget('C1');
        $assigned = [14 => ['costo' => 300, 'target_snapshot' => $snapshot, 'classic_role' => 'C']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::COMPRESSO, $this->roleStatus($result, 'C'));
    }

    // =========================================================================
    // DIFESA
    // =========================================================================

    public function test_difesa_d1_45_is_opportunistico(): void
    {
        $snapshot = $this->pristineTarget('D1'); // 54
        $assigned = [6 => ['costo' => 45, 'target_snapshot' => $snapshot, 'classic_role' => 'D']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::OPPORTUNISTICO, $this->roleStatus($result, 'D'));
    }

    public function test_difesa_d1_54_is_in_piano(): void
    {
        $snapshot = $this->pristineTarget('D1');
        $assigned = [6 => ['costo' => 54, 'target_snapshot' => $snapshot, 'classic_role' => 'D']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::IN_PIANO, $this->roleStatus($result, 'D'));
    }

    public function test_difesa_d1_63_is_aggressivo(): void
    {
        $snapshot = $this->pristineTarget('D1');
        $assigned = [6 => ['costo' => 63, 'target_snapshot' => $snapshot, 'classic_role' => 'D']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::AGGRESSIVO, $this->roleStatus($result, 'D'));
    }

    public function test_difesa_d1_75_is_compresso(): void
    {
        $snapshot = $this->pristineTarget('D1');
        $assigned = [6 => ['costo' => 75, 'target_snapshot' => $snapshot, 'classic_role' => 'D']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::COMPRESSO, $this->roleStatus($result, 'D'));
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_no_purchases_returns_null_role_status(): void
    {
        $result = $this->evaluate([]);

        $this->assertNull($this->roleStatus($result, 'A'));
        $this->assertNull($this->roleStatus($result, 'C'));
        $this->assertNull($this->roleStatus($result, 'D'));
        $this->assertNull($result['global_status']);
    }

    public function test_legacy_row_without_snapshot_is_excluded_from_purchase_delta(): void
    {
        // Row with target_snapshot = null should not contribute to purchase_delta.
        // A1 bought for 600 but no snapshot → pd is null → status driven by fc alone.
        $assigned = [22 => ['costo' => 600, 'target_snapshot' => null, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);

        // fc will show compression, pd is null
        $role = $result['roles']['A'];
        $this->assertNull($role['purchase_delta']);
        // Future compression: A1 is closed, A2-A6 open. A spent=600 compresses them.
        // compression should be in AGGRESSIVO or COMPRESSO range
        $this->assertNotNull($role['future_compression']);
        $this->assertNotNull($role['status']);
        // Whatever state, it should not throw
    }

    public function test_role_fully_closed_has_null_future_compression(): void
    {
        // All A slots bought → future_compression = null
        $snapA1 = $this->pristineTarget('A1');
        $assigned = [
            22 => ['costo' => 462, 'target_snapshot' => $snapA1, 'classic_role' => 'A'],
            23 => ['costo' => 347, 'target_snapshot' => 347, 'classic_role' => 'A'],
            24 => ['costo' => 155, 'target_snapshot' => 155, 'classic_role' => 'A'],
            25 => ['costo' => 78,  'target_snapshot' => 78,  'classic_role' => 'A'],
            26 => ['costo' => 39,  'target_snapshot' => 39,  'classic_role' => 'A'],
            27 => ['costo' => 39,  'target_snapshot' => 39,  'classic_role' => 'A'],
        ];

        $result = $this->evaluate($assigned);

        $this->assertNull($result['roles']['A']['future_compression']);
        $this->assertNull($result['roles']['A']['worst_key_slot_ratio']);
        // But purchase_delta is defined (all have valid snapshots)
        $this->assertNotNull($result['roles']['A']['purchase_delta']);
        $this->assertNotNull($result['roles']['A']['status']);
    }

    public function test_worst_key_slot_triggers_compresso_override(): void
    {
        // A1=650: worst_key A2 drops to ~71% → COMPRESSO floor even if fc were in AGGRESSIVO
        $snapshot = $this->pristineTarget('A1');
        $assigned = [22 => ['costo' => 650, 'target_snapshot' => $snapshot, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);
        $role = $result['roles']['A'];

        // wk should be around 0.71 (below 0.75 threshold)
        $this->assertNotNull($role['worst_key_slot_ratio']);
        $this->assertLessThan(0.75, $role['worst_key_slot_ratio']);
        $this->assertSame(RosaStatusEvaluator::COMPRESSO, $role['status']);
    }

    public function test_worst_key_slot_triggers_rischio_struttura_immediately(): void
    {
        // A1=750: worst_key A2 drops to ~56% → RISCHIO_STRUTTURA hard trigger
        $snapshot = $this->pristineTarget('A1');
        $assigned = [22 => ['costo' => 750, 'target_snapshot' => $snapshot, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);
        $role = $result['roles']['A'];

        $this->assertNotNull($role['worst_key_slot_ratio']);
        $this->assertLessThan(0.60, $role['worst_key_slot_ratio']);
        $this->assertSame(RosaStatusEvaluator::RISCHIO_STRUTTURA, $role['status']);
    }

    // =========================================================================
    // GLOBAL STATUS — hard rules
    // =========================================================================

    public function test_global_is_rischio_struttura_when_a_is_rischio_struttura(): void
    {
        $snapshot = $this->pristineTarget('A1');
        // A1=750 → A=RISCHIO_STRUTTURA
        $assigned = [22 => ['costo' => 750, 'target_snapshot' => $snapshot, 'classic_role' => 'A']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::RISCHIO_STRUTTURA, $result['roles']['A']['status']);
        $this->assertSame(RosaStatusEvaluator::RISCHIO_STRUTTURA, $result['global_status']);
    }

    public function test_global_is_rischio_struttura_when_c_is_rischio_struttura(): void
    {
        // C1=600 → purchase_delta = (600-222)/222 = +170% → RISCHIO_STRUTTURA
        $snapshot = $this->pristineTarget('C1');
        $assigned = [14 => ['costo' => 600, 'target_snapshot' => $snapshot, 'classic_role' => 'C']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::RISCHIO_STRUTTURA, $result['roles']['C']['status']);
        $this->assertSame(RosaStatusEvaluator::RISCHIO_STRUTTURA, $result['global_status']);
    }

    public function test_global_is_at_least_compresso_when_d_is_rischio_struttura(): void
    {
        // D1=200 → massive overspend on D → D=RISCHIO_STRUTTURA, but A/C fine
        $snapshot = $this->pristineTarget('D1');
        $assigned = [6 => ['costo' => 200, 'target_snapshot' => $snapshot, 'classic_role' => 'D']];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::RISCHIO_STRUTTURA, $result['roles']['D']['status']);
        // Global must be at least COMPRESSO
        $globalSeverity = [
            RosaStatusEvaluator::OPPORTUNISTICO    => -1,
            RosaStatusEvaluator::IN_PIANO          =>  0,
            RosaStatusEvaluator::AGGRESSIVO        =>  1,
            RosaStatusEvaluator::COMPRESSO         =>  2,
            RosaStatusEvaluator::RISCHIO_STRUTTURA =>  3,
        ];
        $this->assertGreaterThanOrEqual(
            $globalSeverity[RosaStatusEvaluator::COMPRESSO],
            $globalSeverity[$result['global_status']]
        );
    }

    public function test_global_opportunistico_only_when_all_roles_are_opportunistico(): void
    {
        $snapD1 = $this->pristineTarget('D1');
        $snapC1 = $this->pristineTarget('C1');
        $snapA1 = $this->pristineTarget('A1');

        // All three well under target
        $assigned = [
            6  => ['costo' => 30,  'target_snapshot' => $snapD1, 'classic_role' => 'D'],
            14 => ['costo' => 150, 'target_snapshot' => $snapC1, 'classic_role' => 'C'],
            22 => ['costo' => 350, 'target_snapshot' => $snapA1, 'classic_role' => 'A'],
        ];

        $result = $this->evaluate($assigned);

        $this->assertSame(RosaStatusEvaluator::OPPORTUNISTICO, $result['roles']['D']['status']);
        $this->assertSame(RosaStatusEvaluator::OPPORTUNISTICO, $result['roles']['C']['status']);
        $this->assertSame(RosaStatusEvaluator::OPPORTUNISTICO, $result['roles']['A']['status']);
        $this->assertSame(RosaStatusEvaluator::OPPORTUNISTICO, $result['global_status']);
    }

    // =========================================================================
    // GOALKEEPER
    // =========================================================================

    public function test_goalkeeper_no_blocks_bought_is_opportunistico(): void
    {
        $result = $this->evaluate([]);

        // block_spent = 0, target = 100, ratio = 0 → OPPORTUNISTICO
        $this->assertSame(RosaStatusEvaluator::OPPORTUNISTICO, $result['goalkeeper']['status']);
    }

    public function test_goalkeeper_in_piano_when_block_spent_near_target(): void
    {
        // Buy P1 at cost 95 (ratio = 95/100 = 0.95 → IN_PIANO)
        $assigned = [0 => ['costo' => 95, 'target_snapshot' => null, 'classic_role' => 'P', 'team' => 'JUVENTUS']];
        $plan = $this->makePlan($assigned);

        $result = $this->evaluator->evaluate($this->budget, $plan['slots'], $plan, $assigned);

        $this->assertSame(RosaStatusEvaluator::IN_PIANO, $result['goalkeeper']['status']);
    }

    public function test_goalkeeper_aggressivo_when_block_spent_over_target(): void
    {
        // Buy P1 at cost 130 (ratio = 130/100 = 1.30 → AGGRESSIVO)
        $assigned = [0 => ['costo' => 130, 'target_snapshot' => null, 'classic_role' => 'P', 'team' => 'INTER']];
        $plan = $this->makePlan($assigned);

        $result = $this->evaluator->evaluate($this->budget, $plan['slots'], $plan, $assigned);

        $this->assertSame(RosaStatusEvaluator::AGGRESSIVO, $result['goalkeeper']['status']);
    }

    // =========================================================================
    // OUTPUT STRUCTURE
    // =========================================================================

    public function test_result_has_expected_keys(): void
    {
        $result = $this->evaluate([]);

        $this->assertArrayHasKey('global_status', $result);
        $this->assertArrayHasKey('roles', $result);
        $this->assertArrayHasKey('goalkeeper', $result);

        foreach (['D', 'C', 'A'] as $role) {
            $this->assertArrayHasKey($role, $result['roles']);
            $this->assertArrayHasKey('status',               $result['roles'][$role]);
            $this->assertArrayHasKey('purchase_delta',       $result['roles'][$role]);
            $this->assertArrayHasKey('future_compression',   $result['roles'][$role]);
            $this->assertArrayHasKey('worst_key_slot_ratio', $result['roles'][$role]);
        }

        $this->assertArrayHasKey('status', $result['goalkeeper']);
        $this->assertArrayHasKey('ratio',  $result['goalkeeper']);
    }
}
