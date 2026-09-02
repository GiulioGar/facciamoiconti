<?php

namespace Tests\Unit;

use App\Services\Fantacalcio\AppetibilityCalculator;
use Tests\TestCase;

class AppetibilityCalculatorTest extends TestCase
{
    private AppetibilityCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AppetibilityCalculator();
    }

    // 1. Top consolidato: FVM alto + gol/assist alti + tante presenze → score >70, ranking corretto
    public function test_top_consolidato_scores_high_and_ranked_correctly(): void
    {
        $players = [
            ['external_id' => 1, 'fvm' => 100, 'pv' => 38, 'gf' => 15, 'ass' => 5, 'mv' => 7.80, 'like' => 0, 'dislike' => 0],
            ['external_id' => 2, 'fvm' => 50,  'pv' => 38, 'gf' => 5,  'ass' => 2, 'mv' => 5.50, 'like' => 0, 'dislike' => 0],
            ['external_id' => 3, 'fvm' => 20,  'pv' => 38, 'gf' => 1,  'ass' => 0, 'mv' => 4.00, 'like' => 0, 'dislike' => 0],
        ];

        $scores = $this->calc->computeForRole($players, 'A');

        $this->assertGreaterThan(70, $scores[1], 'Top consolidato deve avere score > 70');
        $this->assertGreaterThan($scores[2], $scores[1], 'Top player > mid player');
        $this->assertGreaterThan($scores[3], $scores[2], 'Mid player > bottom player');
    }

    // 2. Poche presenze: stats eccellenti in 2 partite → dampened vs giocatore regolare con 30 partite
    public function test_low_pv_dampens_excellent_per_game_stats(): void
    {
        // 5 giocatori per percentili significativi; tutti fvm=50 per isolare componente B
        $players = [
            ['external_id' => 1, 'fvm' => 50, 'pv' => 2,  'gf' => 3,  'ass' => 2, 'mv' => 7.5, 'like' => 0, 'dislike' => 0],
            ['external_id' => 2, 'fvm' => 50, 'pv' => 30, 'gf' => 18, 'ass' => 6, 'mv' => 7.0, 'like' => 0, 'dislike' => 0],
            ['external_id' => 3, 'fvm' => 50, 'pv' => 25, 'gf' => 10, 'ass' => 4, 'mv' => 6.5, 'like' => 0, 'dislike' => 0],
            ['external_id' => 4, 'fvm' => 50, 'pv' => 20, 'gf' => 5,  'ass' => 2, 'mv' => 6.0, 'like' => 0, 'dislike' => 0],
            ['external_id' => 5, 'fvm' => 50, 'pv' => 15, 'gf' => 2,  'ass' => 1, 'mv' => 5.5, 'like' => 0, 'dislike' => 0],
        ];

        $scores = $this->calc->computeForRole($players, 'A');

        // Player 1 ha gf_pg=1.5 (top del campione) ma rel=0.10 → B smorzato verso la media
        // Player 2 ha gf_pg=0.6 (secondo) con rel=1.0 → B pieno → vince
        $this->assertLessThan($scores[2], $scores[1], 'rel=0.1 (pv=2) smorza stats eccellenti: score < pv=30 con stats solide');
    }

    // 3. Senza storico: fallback alla media ruolo per B → score neutro tra top e bottom
    public function test_player_without_stats_gets_neutral_score(): void
    {
        $players = [
            ['external_id' => 1, 'fvm' => 50, 'pv' => null, 'gf' => null, 'ass' => null, 'mv' => null, 'like' => 0, 'dislike' => 0],
            ['external_id' => 2, 'fvm' => 50, 'pv' => 20,   'gf' => 8,    'ass' => 4,    'mv' => 8.00, 'like' => 0, 'dislike' => 0],
            ['external_id' => 3, 'fvm' => 50, 'pv' => 20,   'gf' => 1,    'ass' => 0,    'mv' => 4.00, 'like' => 0, 'dislike' => 0],
        ];

        $scores = $this->calc->computeForRole($players, 'A');

        $this->assertGreaterThanOrEqual(0,   $scores[1], 'Score >= 0');
        $this->assertLessThanOrEqual(100, $scores[1], 'Score <= 100');
        // Nessuno storico → B = avgPerf (media tra top e bottom) → score intermedio
        $this->assertGreaterThan($scores[3], $scores[1], 'Senza storico > bottom');
        $this->assertLessThan($scores[2], $scores[1], 'Senza storico < top');
    }

    // 4. Clamp like/dislike: net > 10 → C=10, net < -10 → C=-10
    public function test_like_dislike_clamped_at_ten(): void
    {
        $base = ['fvm' => 50, 'pv' => 20, 'gf' => 5, 'ass' => 2, 'mv' => 5.0];
        $players = [
            array_merge($base, ['external_id' => 1, 'like' => 100, 'dislike' => 0]),   // net=100 → C=+10
            array_merge($base, ['external_id' => 2, 'like' => 0,   'dislike' => 100]), // net=-100 → C=-10
            array_merge($base, ['external_id' => 3, 'like' => 11,  'dislike' => 0]),   // net=11 → clamp → C=+10
            array_merge($base, ['external_id' => 4, 'like' => 10,  'dislike' => 0]),   // net=10 → esattamente C=+10
        ];

        $scores = $this->calc->computeForRole($players, 'A');

        // Players 1, 3, 4 hanno tutti C=+10 → score identico
        $this->assertEquals($scores[1], $scores[3], 'like=100 e like=11 producono stesso bonus (clamp)');
        $this->assertEquals($scores[1], $scores[4], 'like=100 e like=10 producono stesso bonus (clamp)');
        // La differenza tra max-like e max-dislike deve essere 20 punti (da +10 a -10)
        $this->assertEqualsWithDelta(20.0, $scores[1] - $scores[2], 0.01, 'Spread max-like vs max-dislike = 20 punti');
    }

    // 5. Score sempre 0-100 anche con valori estremi
    public function test_score_always_in_range_zero_to_one_hundred(): void
    {
        $players = [
            ['external_id' => 1, 'fvm' => 9999, 'pv' => 38,   'gf' => 50,   'ass' => 20,  'gs' => 0,  'mv' => 10.0, 'like' => 999, 'dislike' => 0],
            ['external_id' => 2, 'fvm' => 1,    'pv' => 1,    'gf' => 0,    'ass' => 0,   'gs' => 30, 'mv' => 1.0,  'like' => 0,   'dislike' => 999],
            ['external_id' => 3, 'fvm' => 0,    'pv' => null, 'gf' => null, 'ass' => null,'gs' => null,'mv' => null,'like' => 0,   'dislike' => 999],
        ];

        $scores = $this->calc->computeForRole($players, 'A');

        foreach ($scores as $extId => $score) {
            $this->assertGreaterThanOrEqual(0,   $score, "Score giocatore {$extId} deve essere >= 0");
            $this->assertLessThanOrEqual(100, $score, "Score giocatore {$extId} deve essere <= 100");
        }
    }
}
