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
}
