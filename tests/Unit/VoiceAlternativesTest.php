<?php

namespace Tests\Unit;

use App\Support\VoiceAlternatives;
use Tests\TestCase;

class VoiceAlternativesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['scan.enabled' => false]);
    }

    public function test_consistent_alternatives_are_accepted_without_asking(): void
    {
        // Different raw texts, same command: no doubt.
        $this->assertSame(
            ['type' => 'navigate', 'target' => 'kamus', 'index' => null, 'decision' => 'accept'],
            VoiceAlternatives::decide(['buka kamus', 'Kamus.', 'ke kamus dong']),
        );
        $this->assertSame(
            ['type' => 'navigate', 'target' => 'game', 'index' => null, 'decision' => 'accept'],
            VoiceAlternatives::decide(['masuk ke game', 'masuk ke gem', 'masuk kegem']),
        );
    }

    public function test_unknown_other_alternatives_do_not_cause_doubt(): void
    {
        $this->assertSame(
            ['type' => 'start', 'target' => null, 'index' => null, 'decision' => 'accept'],
            VoiceAlternatives::decide(['mulai', 'mulia', 'mula']),
        );
    }

    public function test_conflicting_known_alternatives_ask_a_numbered_choice(): void
    {
        $this->assertSame(
            [
                'type' => 'clarify', 'target' => null, 'index' => null, 'decision' => 'choose',
                'candidates' => [
                    ['type' => 'navigate', 'target' => 'ujian', 'index' => null, 'label' => 'ujian'],
                    ['type' => 'navigate', 'target' => 'latihan-isian', 'index' => null, 'label' => 'isian'],
                ],
            ],
            VoiceAlternatives::decide(['ujian', 'isian', 'ujian dong']),
        );
    }

    public function test_game_answers_are_accepted_unless_another_alternative_is_a_different_choice(): void
    {
        $this->assertSame(
            ['type' => 'choose_number', 'target' => null, 'index' => 2, 'decision' => 'accept'],
            VoiceAlternatives::decide(['jawab tiga', 'nomor 3', 'jawab tika']),
        );
        $this->assertSame(
            ['type' => 'choose_number', 'target' => null, 'index' => 0, 'decision' => 'accept'],
            VoiceAlternatives::decide(['A', 'ah', 'a.']),
        );

        $result = VoiceAlternatives::decide(['nomor tiga', 'nomor dua']);
        $this->assertSame('choose', $result['decision']);
        $this->assertSame(['nomor 3', 'nomor 2'], array_column($result['candidates'], 'label'));
        $this->assertSame([2, 1], array_column($result['candidates'], 'index'));
    }

    public function test_top_unknown_but_another_known_asks_to_confirm_that_one(): void
    {
        $this->assertSame(
            ['type' => 'more', 'target' => null, 'index' => null, 'decision' => 'confirm', 'heard_index' => 1],
            VoiceAlternatives::decide(['lanjar', 'lanjut', 'lancar']),
        );
    }

    public function test_nothing_known_returns_the_top_parse(): void
    {
        $this->assertSame(
            ['type' => 'unknown', 'target' => null, 'index' => null, 'decision' => 'none'],
            VoiceAlternatives::decide(['apa kabar', 'apa kabarnya', 'ada kabar']),
        );
        $this->assertSame(['type' => 'unknown', 'target' => null, 'index' => null, 'decision' => 'none'], VoiceAlternatives::decide([]));

        // A clarify from one-letter tolerance stays a clarify.
        $result = VoiceAlternatives::decide(['usian', 'usia']);
        $this->assertSame(['clarify', 'none'], [$result['type'], $result['decision']]);
        $this->assertCount(2, $result['candidates']);
    }

    public function test_only_three_alternatives_are_looked_at(): void
    {
        $this->assertSame('accept', VoiceAlternatives::decide(['kamus', 'kamus', 'kamus', 'kartu'])['decision']);
    }
}
