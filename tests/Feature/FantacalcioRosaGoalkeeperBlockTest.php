<?php

namespace Tests\Feature;

use App\Models\FantaListone;
use App\Models\FantaRosa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FantacalcioRosaGoalkeeperBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_goalkeeper_of_a_team_keeps_the_block_cost(): void
    {
        $this->addPlayer(1001, 'P', 'Napoli', 25, 0);

        $this->addToRosa(1001, 'P', 0, 25);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 1001,
            'costo' => 25,
        ]);
    }

    public function test_second_goalkeeper_of_a_team_is_saved_as_a_zero_cost_cover(): void
    {
        $this->addPlayer(1001, 'P', 'Napoli', 25, 0);
        $this->addPlayer(1002, 'P', '  napoli  ', 25, 0);

        $this->addToRosa(1001, 'P', 0, 25);
        $this->addToRosa(1002, 'P', 1, 10);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 1002,
            'costo' => 0,
        ]);
        $this->assertSame(25, (int) FantaRosa::sum('costo'));
    }

    public function test_different_goalkeeper_teams_are_separate_blocks(): void
    {
        $this->addPlayer(1001, 'P', 'Napoli', 25, 0);
        $this->addPlayer(1002, 'P', 'Monza', 7, 0);

        $this->addToRosa(1001, 'P', 0, 25);
        $this->addToRosa(1002, 'P', 1, 7);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 1001,
            'costo' => 25,
        ]);
        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 1002,
            'costo' => 7,
        ]);
        $this->assertSame(32, (int) FantaRosa::sum('costo'));
    }

    public function test_non_goalkeeper_roles_keep_the_requested_cost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['D', 'C', 'A'] as $offset => $role) {
            $externalId = 2001 + $offset;
            $this->addPlayer($externalId, $role, 'Squadra ' . $role, 10, 0);

            $this->post(route('fantacalcio.rosa.add'), [
                'external_id' => $externalId,
                'role_token' => $role,
                'slot_index' => 6 + $offset,
                'costo' => 10,
            ])->assertSessionHas('success');
        }

        $this->assertSame(30, (int) FantaRosa::sum('costo'));
    }

    public function test_a1_bought_at_130_saves_initial_target_and_massimo_snapshots(): void
    {
        $this->addPlayer(3001, 'A', 'Napoli', 130, 0);

        $this->addToRosa(3001, 'A', 22, 130);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 3001,
            'target_snapshot' => 114,
            'massimo_snapshot' => 143,
        ]);
    }

    public function test_a1_bought_at_145_keeps_the_same_pre_purchase_snapshots(): void
    {
        $this->addPlayer(3002, 'A', 'Inter', 145, 0);

        $this->addToRosa(3002, 'A', 22, 145);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 3002,
            'target_snapshot' => 114,
            'massimo_snapshot' => 143,
        ]);
    }

    public function test_a1_bought_below_target_keeps_the_same_pre_purchase_snapshots(): void
    {
        $this->addPlayer(3003, 'A', 'Milan', 95, 0);

        $this->addToRosa(3003, 'A', 22, 95);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 3003,
            'target_snapshot' => 114,
            'massimo_snapshot' => 143,
        ]);
    }

    public function test_second_purchase_after_a1_bought_at_130_saves_recalculated_a2_snapshots(): void
    {
        $this->addPlayer(3004, 'A', 'Napoli', 130, 0);
        $this->addPlayer(3005, 'A', 'Roma', 78, 0);

        $this->addToRosa(3004, 'A', 22, 130);
        $this->addToRosa(3005, 'A', 23, 78);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 3005,
            'target_snapshot' => 78,
            'massimo_snapshot' => 90,
        ]);
    }

    public function test_goalkeeper_main_has_null_snapshots(): void
    {
        $this->addPlayer(3006, 'P', 'Napoli', 25, 0);

        $this->addToRosa(3006, 'P', 0, 25);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 3006,
            'target_snapshot' => null,
            'massimo_snapshot' => null,
        ]);
    }

    public function test_goalkeeper_cover_has_null_snapshots(): void
    {
        $this->addPlayer(3007, 'P', 'Napoli', 25, 0);
        $this->addPlayer(3008, 'P', ' napoli ', 25, 0);

        $this->addToRosa(3007, 'P', 0, 25);
        $this->addToRosa(3008, 'P', 1, 10);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 3008,
            'costo' => 0,
            'target_snapshot' => null,
            'massimo_snapshot' => null,
        ]);
    }

    public function test_existing_records_still_use_the_pre_purchase_reference_of_the_requested_slot(): void
    {
        $this->addPlayer(3009, 'D', 'Atalanta', 10, 0);
        $this->addPlayer(3010, 'C', 'Lazio', 20, 0);
        $this->addPlayer(3011, 'A', 'Juventus', 99, 0);

        $this->addToRosa(3009, 'D', 6, 10);
        $this->addToRosa(3010, 'C', 14, 20);

        $expected = $this->plannerSlot(22);
        $this->addToRosa(3011, 'A', 22, 99);

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id' => 3011,
            'target_snapshot' => $expected['target'],
            'massimo_snapshot' => $expected['massimo'],
        ]);
    }

    private function addToRosa(int $externalId, string $role, int $slotIndex, int $cost): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('fantacalcio.rosa.add'), [
                'external_id' => $externalId,
                'role_token' => $role,
                'slot_index' => $slotIndex,
                'costo' => $cost,
            ])
            ->assertSessionHas('success');
    }

    private function addPlayer(int $externalId, string $role, string $team, int $fvm, int $state): void
    {
        FantaListone::create([
            'external_id' => $externalId,
            'ruolo' => $role,
            'ruolo_esteso' => $role,
            'nome' => 'Giocatore ' . $externalId,
            'squadra' => $team,
            'fvm' => $fvm,
            'stato' => $state,
        ]);
    }

    private function plannerSlot(int $slotIndex): array
    {
        $goalkeeperSlots = array_map(function (array $slot) {
            return array_merge($slot, [
                'role_token' => 'P',
                'title' => 'Portiere ' . ((int) $slot['index'] + 1),
                'level' => $slot['index'] === 0 ? 'Top' : 'Low',
                'hint' => 'Slot tecnico portieri, predisposto per futuri blocchi/treni.',
            ]);
        }, config('fantacalcio.rosa_goalkeeper_slots', []));

        $dcaSlots = [];
        foreach (config('fantacalcio.rosa_dca_slots', []) as $roleSlots) {
            foreach ($roleSlots as $slot) {
                $dcaSlots[] = array_merge($slot, [
                    'role_token' => $slot['role'],
                    'title' => $slot['label'],
                ]);
            }
        }

        $slots = array_merge($goalkeeperSlots, $dcaSlots);
        $assignedByIndex = [];

        foreach (FantaRosa::orderBy('slot_index')->get([
            'slot_index', 'external_id', 'nome', 'squadra', 'costo', 'ruolo_esteso', 'classic_role'
        ]) as $row) {
            $assignedByIndex[(int) $row->slot_index] = [
                'ext_id' => $row->external_id,
                'nome' => $row->nome,
                'team' => $row->squadra,
                'roles' => $row->ruolo_esteso,
                'classic_role' => $row->classic_role,
                'costo' => (int) $row->costo,
            ];
        }

        $result = app(\App\Services\Fantacalcio\RosaBudgetCalculator::class)
            ->calculate(500, $slots, $assignedByIndex);

        foreach ($result['slots'] as $slot) {
            if ((int) $slot['index'] === $slotIndex) {
                return $slot;
            }
        }

        $this->fail('Planner slot not found.');
    }
}
