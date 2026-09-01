<?php

namespace Tests\Feature;

use App\Models\FantaListone;
use App\Models\FantaRosa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FantacalcioRosaRemoveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function addPlayer(int $externalId, string $role, string $team, int $fvm = 10): FantaListone
    {
        return FantaListone::create([
            'external_id' => $externalId,
            'ruolo'       => $role,
            'ruolo_esteso'=> $role,
            'nome'        => 'Giocatore ' . $externalId,
            'squadra'     => $team,
            'fvm'         => $fvm,
            'stato'       => 0,
        ]);
    }

    private function addToRosa(int $externalId, string $role, int $slotIndex, int $cost): void
    {
        $this->post(route('fantacalcio.rosa.add'), [
            'external_id' => $externalId,
            'role_token'  => $role,
            'slot_index'  => $slotIndex,
            'costo'       => $cost,
        ])->assertSessionHas('success');
    }

    private function removeFromRosa(int $externalId): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('fantacalcio.rosa.remove'), [
            'external_id' => $externalId,
        ]);
    }

    public function test_remove_dca_player_frees_slot_and_restores_listone(): void
    {
        $this->addPlayer(2001, 'D', 'Juventus');
        $this->addToRosa(2001, 'D', 6, 15);

        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 2001]);
        $this->assertDatabaseHas('fanta_listone', ['external_id' => 2001, 'stato' => 1]);

        $this->removeFromRosa(2001)->assertSessionHas('success');

        $this->assertDatabaseMissing('fanta_rosa', ['external_id' => 2001]);
        $this->assertDatabaseMissing('fanta_rosa', ['slot_index' => 6]);
        $this->assertDatabaseHas('fanta_listone', ['external_id' => 2001, 'stato' => 0]);
    }

    public function test_remove_dca_player_with_snapshots_deletes_row_and_snapshots(): void
    {
        $this->addPlayer(2002, 'A', 'Inter', 130);
        $this->addToRosa(2002, 'A', 22, 130);

        $rosa = FantaRosa::where('external_id', 2002)->first();
        $this->assertNotNull($rosa->target_snapshot);
        $this->assertNotNull($rosa->massimo_snapshot);

        $this->removeFromRosa(2002)->assertSessionHas('success');

        $this->assertDatabaseMissing('fanta_rosa', ['external_id' => 2002]);
        $this->assertDatabaseHas('fanta_listone', ['external_id' => 2002, 'stato' => 0]);
    }

    public function test_remove_goalkeeper_cover_leaves_main_cost_intact(): void
    {
        $this->addPlayer(3001, 'P', 'Napoli', 25);
        $this->addPlayer(3002, 'P', 'Napoli', 5);

        $this->addToRosa(3001, 'P', 0, 25);
        $this->addToRosa(3002, 'P', 1, 5);

        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 3001, 'costo' => 25]);
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 3002, 'costo' => 0]);

        $this->removeFromRosa(3002)->assertSessionHas('success');

        $this->assertDatabaseMissing('fanta_rosa', ['external_id' => 3002]);
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 3001, 'costo' => 25]);
        $this->assertDatabaseHas('fanta_listone', ['external_id' => 3002, 'stato' => 0]);
    }

    public function test_remove_goalkeeper_main_with_one_cover_transfers_cost(): void
    {
        $this->addPlayer(4001, 'P', 'Roma', 20);
        $this->addPlayer(4002, 'P', 'Roma', 3);

        $this->addToRosa(4001, 'P', 0, 20);
        $this->addToRosa(4002, 'P', 1, 3);

        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 4001, 'costo' => 20]);
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 4002, 'costo' => 0]);

        $this->removeFromRosa(4001)->assertSessionHas('success');

        $this->assertDatabaseMissing('fanta_rosa', ['external_id' => 4001]);
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 4002, 'costo' => 20]);
        $this->assertDatabaseHas('fanta_listone', ['external_id' => 4001, 'stato' => 0]);
    }

    public function test_remove_goalkeeper_main_with_multiple_covers_transfers_cost_to_oldest(): void
    {
        $this->addPlayer(5001, 'P', 'Milan', 30);
        $this->addPlayer(5002, 'P', 'Milan', 3);
        $this->addPlayer(5003, 'P', 'Milan', 2);

        $this->addToRosa(5001, 'P', 0, 30);
        $this->addToRosa(5002, 'P', 1, 3);
        $this->addToRosa(5003, 'P', 2, 2);

        $cover1Id = FantaRosa::where('external_id', 5002)->value('id');
        $cover2Id = FantaRosa::where('external_id', 5003)->value('id');
        $this->assertLessThan($cover2Id, $cover1Id);

        $this->removeFromRosa(5001)->assertSessionHas('success');

        $this->assertDatabaseMissing('fanta_rosa', ['external_id' => 5001]);
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 5002, 'costo' => 30]);
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 5003, 'costo' => 0]);
    }

    public function test_remove_goalkeeper_main_without_covers_removes_block_cost(): void
    {
        $this->addPlayer(6001, 'P', 'Lazio', 18);
        $this->addToRosa(6001, 'P', 0, 18);

        $this->removeFromRosa(6001)->assertSessionHas('success');

        $this->assertDatabaseMissing('fanta_rosa', ['external_id' => 6001]);
        $this->assertDatabaseHas('fanta_listone', ['external_id' => 6001, 'stato' => 0]);
        $this->assertSame(0, (int) FantaRosa::sum('costo'));
    }

    public function test_promoted_cover_has_null_snapshots(): void
    {
        $this->addPlayer(7001, 'P', 'Fiorentina', 22);
        $this->addPlayer(7002, 'P', 'Fiorentina', 4);

        $this->addToRosa(7001, 'P', 0, 22);
        $this->addToRosa(7002, 'P', 1, 4);

        $this->removeFromRosa(7001)->assertSessionHas('success');

        $this->assertDatabaseHas('fanta_rosa', [
            'external_id'     => 7002,
            'costo'           => 22,
            'target_snapshot' => null,
            'massimo_snapshot'=> null,
        ]);
    }

    public function test_legacy_null_classic_role_goalkeeper_applies_block_transfer_logic(): void
    {
        $this->addPlayer(8001, 'P', 'Torino', 15);
        $this->addPlayer(8002, 'P', 'Torino', 3);

        FantaRosa::create([
            'external_id'     => 8001,
            'ruolo_esteso'    => 'P',
            'nome'            => 'Giocatore 8001',
            'squadra'         => 'Torino',
            'costo'           => 15,
            'classic_role'    => null,
            'slot_index'      => 0,
            'target_snapshot' => null,
            'massimo_snapshot'=> null,
        ]);
        FantaListone::where('external_id', 8001)->update(['stato' => 1]);

        FantaRosa::create([
            'external_id'     => 8002,
            'ruolo_esteso'    => 'P',
            'nome'            => 'Giocatore 8002',
            'squadra'         => 'Torino',
            'costo'           => 0,
            'classic_role'    => null,
            'slot_index'      => 1,
            'target_snapshot' => null,
            'massimo_snapshot'=> null,
        ]);
        FantaListone::where('external_id', 8002)->update(['stato' => 1]);

        $this->removeFromRosa(8001)->assertSessionHas('success');

        $this->assertDatabaseMissing('fanta_rosa', ['external_id' => 8001]);
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 8002, 'costo' => 15]);
        $this->assertDatabaseHas('fanta_listone', ['external_id' => 8001, 'stato' => 0]);
    }

    public function test_remove_nonexistent_player_returns_controlled_error(): void
    {
        $this->addPlayer(2010, 'D', 'Spezia');
        $this->addToRosa(2010, 'D', 6, 5);
        $countBefore = FantaRosa::count();

        $this->removeFromRosa(99999)->assertSessionHas('error');

        $this->assertSame($countBefore, FantaRosa::count());
        $this->assertDatabaseHas('fanta_rosa', ['external_id' => 2010]);
    }

    public function test_invariant_no_two_goalkeepers_same_team_with_positive_cost(): void
    {
        $this->addPlayer(9001, 'P', 'Atalanta', 25);
        $this->addPlayer(9002, 'P', 'Atalanta', 5);
        $this->addPlayer(9003, 'P', 'Atalanta', 2);

        $this->addToRosa(9001, 'P', 0, 25);
        $this->addToRosa(9002, 'P', 1, 5);
        $this->addToRosa(9003, 'P', 2, 2);

        $this->removeFromRosa(9001)->assertSessionHas('success');

        $goalkeeperSlots = array_column(config('fantacalcio.rosa_goalkeeper_slots', []), 'index');
        $atalantaWithCost = FantaRosa::whereIn('slot_index', $goalkeeperSlots)
            ->where('costo', '>', 0)
            ->get()
            ->filter(fn($r) => mb_strtoupper(trim($r->squadra)) === 'ATALANTA');

        $this->assertCount(1, $atalantaWithCost);
    }
}
