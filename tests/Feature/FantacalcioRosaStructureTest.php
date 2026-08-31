<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FantacalcioRosaStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rosa_uses_the_new_28_slot_structure()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('fantacalcio.rosa'));

        $response->assertOk();
        $response->assertViewHas('slots', function ($slots) {
            if (count($slots) !== 28) {
                return false;
            }

            $expectedRoles = array_merge(
                array_fill(0, 6, 'P'),
                array_fill(0, 8, 'D'),
                array_fill(0, 8, 'C'),
                array_fill(0, 6, 'A')
            );

            foreach ($slots as $index => $slot) {
                if (!isset($slot['index'], $slot['role_token'])) {
                    return false;
                }

                if ((int) $slot['index'] !== $index) {
                    return false;
                }

                if ($slot['role_token'] !== $expectedRoles[$index]) {
                    return false;
                }

                if ($index < 6) {
                    foreach (['slot_code', 'train_id', 'train_role', 'strategic_weight', 'min_cost'] as $field) {
                        if (!array_key_exists($field, $slot)) {
                            return false;
                        }
                    }

                    if ($slot['slot_code'] !== 'P' . ($index + 1)
                        || $slot['train_id'] !== null
                        || $slot['train_role'] !== null
                        || (float) $slot['strategic_weight'] <= 0
                        || (int) $slot['min_cost'] !== 1
                    ) {
                        return false;
                    }
                }
            }

            return true;
        });
    }
}
