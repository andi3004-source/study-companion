<?php

namespace Tests\Feature;

use App\Enums\ErrorCategory;
use App\Livewire\Games\ErrorHunter;
use App\Livewire\ProgressOverview;
use App\Models\User;
use App\Support\ErrorGameItems;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ErrorHunterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ErrorGameItems::flush();
        config(['game.items_path' => base_path('tests/Fixtures/kesalahan_game.json')]);
    }

    private function user(): User
    {
        return User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    private function game(): Testable
    {
        return Livewire::actingAs($this->user())->test(ErrorHunter::class);
    }

    private function correctOption(Testable $game): string
    {
        return ErrorGameItems::find($game->get('questions')[$game->get('index')]['id'])['kategori'];
    }

    private function wrongOption(Testable $game): string
    {
        $correct = $this->correctOption($game);

        return collect($game->get('questions')[$game->get('index')]['options'])->first(fn ($o) => $o !== $correct);
    }

    /** @var string[] temp question banks written by this test, removed in tearDown */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->tempFiles, 'is_file'));

        parent::tearDown();
    }

    /** Points the game at a temp question bank holding only $entries. */
    private function useBank(array $entries): void
    {
        $path = tempnam(sys_get_temp_dir(), 'game');
        file_put_contents($path, json_encode($entries, JSON_UNESCAPED_UNICODE));
        $this->tempFiles[] = $path;

        config(['game.items_path' => $path]);
        ErrorGameItems::flush();
    }

    /** A game whose only question is fixture item $itemId (round state is #[Locked], so it can't just be set). */
    private function gameOn(int $itemId): Testable
    {
        $entries = json_decode(file_get_contents(base_path('tests/Fixtures/kesalahan_game.json')), true);
        $this->useBank(array_values(array_filter($entries, fn ($e) => $e['id'] === $itemId)));

        return $this->game();
    }

    public function test_a_round_has_ten_distinct_questions_with_unique_relevant_options(): void
    {
        $questions = $this->game()->get('questions');

        $this->assertCount(10, $questions);
        $this->assertCount(10, array_unique(array_column($questions, 'id')));

        $available = ErrorGameItems::categories();

        foreach ($questions as $question) {
            $correct = ErrorGameItems::find($question['id'])['kategori'];

            $this->assertCount(4, $question['options']);
            $this->assertSame($question['options'], array_values(array_unique($question['options'])));
            $this->assertContains($correct, $question['options']);

            foreach ($question['options'] as $option) {
                // Only categories that have questions (never e.g. 'nada'), and 'lainnya' only as the answer.
                $this->assertContains($option, $available);
                if ($option === 'lainnya') {
                    $this->assertSame('lainnya', $correct);
                }
            }
        }
    }

    public function test_a_small_category_pool_means_fewer_options_not_irrelevant_ones(): void
    {
        $this->useBank([
            ['id' => 1, 'kategori' => 'bu_atau_mei', 'salah' => '我不有钱。', 'benar' => '我没有钱。', 'arti_id' => '', 'penjelasan' => '', 'hanzi_di_luar_hsk1' => [], 'status' => 'siap'],
            ['id' => 2, 'kategori' => 'shi_dengan_kata_sifat', 'salah' => '我是高兴。', 'benar' => '我很高兴。', 'arti_id' => '', 'penjelasan' => '', 'hanzi_di_luar_hsk1' => [], 'status' => 'siap'],
            ['id' => 3, 'kategori' => 'lainnya', 'salah' => '你吗好？', 'benar' => '你好吗？', 'arti_id' => '', 'penjelasan' => '', 'hanzi_di_luar_hsk1' => [], 'status' => 'siap'],
        ]);

        $questions = collect($this->game()->get('questions'))->keyBy('id');

        $this->assertCount(3, $questions); // fewer items than a round: all of them
        $this->assertEqualsCanonicalizing(['bu_atau_mei', 'shi_dengan_kata_sifat'], $questions[1]['options']);
        $this->assertEqualsCanonicalizing(['bu_atau_mei', 'shi_dengan_kata_sifat', 'lainnya'], $questions[3]['options']);
    }

    public function test_correct_and_wrong_answers_score_and_streak(): void
    {
        $game = $this->game();

        $game->call('answer', $this->correctOption($game))
            ->assertSet('score', 1)->assertSet('streak', 1)
            ->assertSee('✓ Benar!');

        $game->call('next')->call('answer', $this->correctOption($game))
            ->assertSet('score', 2)->assertSet('streak', 2)->assertSet('bestStreak', 2);

        $game->call('next')->call('answer', $this->wrongOption($game))
            ->assertSet('score', 2)
            ->assertSet('streak', 0) // reset on a wrong answer
            ->assertSet('bestStreak', 2)
            ->assertSee('✗ Salah. Jawabannya:');

        $game->call('next')->call('answer', $this->correctOption($game))
            ->assertSet('score', 3)->assertSet('streak', 1);
    }

    public function test_an_answer_counts_once_and_must_be_one_of_the_options(): void
    {
        $game = $this->game();

        $game->call('answer', 'nada'); // not an option in this fixture
        $game->assertSet('chosen', null)->assertSet('score', 0);

        $game->call('next')->assertSet('index', 0); // can't skip without answering

        $correct = $this->correctOption($game);
        $game->call('answer', $correct)->call('answer', $correct)->call('answer', $this->wrongOption($game));

        $game->assertSet('score', 1)->assertSet('streak', 1)->assertSet('chosen', $correct);
    }

    public function test_round_state_cannot_be_changed_from_the_browser(): void
    {
        foreach (['score' => 10, 'index' => 9, 'finished' => true, 'questions' => []] as $property => $value) {
            try {
                $this->game()->set($property, $value);
                $this->fail("{$property} should be locked");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_feedback_shows_correction_explanation_and_outside_hsk1_note_only_when_present(): void
    {
        $this->gameOn(10)->call('answer', 'kata_penggolong')
            ->assertSee('这是一只猫。')
            ->assertSee('Hewan memakai kata penggolong 只.')
            ->assertSee('Hanzi di luar HSK 1: 只');

        $this->gameOn(1)->call('answer', 'bu_atau_mei')
            ->assertSee('我没有钱。')
            ->assertDontSee('Hanzi di luar HSK 1');
    }

    public function test_draft_question_shows_its_badge(): void
    {
        $this->gameOn(3)->assertSee('Soal draf, sedang diverifikasi');
        $this->gameOn(1)->assertDontSee('Soal draf, sedang diverifikasi');
    }

    public function test_end_screen_shows_score_weakest_category_with_example_and_plays_again(): void
    {
        $game = $this->game();
        $missed = null;

        for ($i = 0; $i < 10; $i++) {
            if ($i === 4) {
                $missed = ErrorGameItems::find($game->get('questions')[$i]['id']);
                $game->call('answer', $this->wrongOption($game));
            } else {
                $game->call('answer', $this->correctOption($game));
            }
            $game->call('next');
        }

        $label = ErrorCategory::fromLoose($missed['kategori'])->label();

        $game->assertSet('finished', true)
            ->assertSee('9/10')
            ->assertSee('Paling sering salah')
            ->assertSee($label)
            ->assertSee($missed['salah'])
            ->assertSee($missed['benar'])
            ->assertSee('Main lagi');

        $game->call('restart')
            ->assertSet('finished', false)
            ->assertSet('score', 0)
            ->assertSet('index', 0)
            ->assertSet('missed', [])
            ->assertCount('questions', 10);
    }

    public function test_perfect_round_says_so(): void
    {
        $game = $this->game();

        for ($i = 0; $i < 10; $i++) {
            $game->call('answer', $this->correctOption($game))->call('next');
        }

        $game->assertSee('10/10')->assertSee('Semua jawaban benar')->assertDontSee('Paling sering salah');
    }

    public function test_missing_data_shows_a_clear_message(): void
    {
        config(['game.items_path' => base_path('tests/Fixtures/does_not_exist.json')]);
        ErrorGameItems::flush();

        $this->game()->assertSee('Data game belum tersedia');
    }

    public function test_route_navigation_and_progress_link(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $user = $this->user();

        $this->actingAs($user)->get('/game/pemburu-kesalahan')
            ->assertOk()
            ->assertSee('Pemburu Kesalahan');

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('href="'.route('game').'"', false)
            ->assertSee('Game');

        Livewire::actingAs($user)->test(ProgressOverview::class)
            ->assertSee('Latih lewat game Pemburu Kesalahan');
    }
}
