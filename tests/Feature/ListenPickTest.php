<?php

namespace Tests\Feature;

use App\Livewire\Games\ListenPick;
use App\Models\GameAttempt;
use App\Models\MandarinHskWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ListenPickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        // No closed object list: only the HSK words below are in the pool.
        config(['katabicara.path' => base_path('tests/Fixtures/does_not_exist.json')]);
    }

    /** Eight HSK 1 words with distinct Indonesian meanings, plus words that must never be used. */
    private function seedWords(): void
    {
        foreach ([
            ['我', 'wǒ', 'saya'], ['你', 'nǐ', 'kamu'], ['他', 'tā', 'dia'], ['好', 'hǎo', 'baik'],
            ['大', 'dà', 'besar'], ['小', 'xiǎo', 'kecil'], ['水', 'shuǐ', 'air'], ['猫', 'māo', 'kucing'],
        ] as [$hanzi, $pinyin, $arti]) {
            MandarinHskWord::create(['hanzi' => $hanzi, 'pinyin' => $pinyin, 'meaning_en' => 'x', 'meaning_id' => $arti, 'hsk_level_old' => 1]);
        }

        // No Indonesian meaning, and a different level: never a question or an option.
        MandarinHskWord::create(['hanzi' => '吗', 'pinyin' => 'ma', 'meaning_en' => 'question particle', 'meaning_id' => null, 'hsk_level_old' => 1]);
        MandarinHskWord::create(['hanzi' => '经济', 'pinyin' => 'jīngjì', 'meaning_en' => 'economy', 'meaning_id' => 'ekonomi', 'hsk_level_old' => 4]);
    }

    private function user(): User
    {
        return User::factory()->create(['start_date' => now()->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    private function game(): Testable
    {
        return Livewire::actingAs($this->user())->test(ListenPick::class);
    }

    private function correctIndex(Testable $game): int
    {
        $question = $game->get('questions')[$game->get('index')];

        return array_search($question['arti'], $question['options'], true);
    }

    private function wrongIndex(Testable $game): int
    {
        return ($this->correctIndex($game) + 1) % 4;
    }

    public function test_a_round_has_five_distinct_questions_with_four_unique_options_including_the_answer(): void
    {
        $this->seedWords();
        $questions = $this->game()->get('questions');

        $this->assertCount(5, $questions);
        $this->assertCount(5, array_unique(array_column($questions, 'hanzi')));

        foreach ($questions as $question) {
            $this->assertCount(4, $question['options']);
            $this->assertCount(4, array_unique($question['options']));
            $this->assertContains($question['arti'], $question['options']);
        }
    }

    public function test_words_without_an_indonesian_meaning_or_from_another_level_are_never_used(): void
    {
        $this->seedWords();

        foreach (range(1, 5) as $ignored) {
            foreach ($this->game()->get('questions') as $question) {
                $this->assertNotContains($question['hanzi'], ['吗', '经济']);
                $this->assertNotContains('ekonomi', $question['options']);
                $this->assertNotContains('', $question['options']);
            }
        }
    }

    public function test_correct_and_wrong_answers_score_and_end_screen_lists_misses(): void
    {
        $this->seedWords();
        $game = $this->game();

        $game->set('pilihan', (string) $this->correctIndex($game))->call('jawab')
            ->assertSet('score', 1)
            ->assertSee('✓')->assertSee('Benar.')
            ->assertSee('Soal berikutnya');

        $missed = $game->get('questions')[1];
        $game->call('next')->set('pilihan', (string) $this->wrongIndex($game))->call('jawab')
            ->assertSet('score', 1)
            ->assertSee('✗')->assertSee('Salah, jawabannya '.$missed['arti'].'.');

        foreach (range(2, 4) as $ignored) {
            $game->call('next')->set('pilihan', (string) $this->correctIndex($game))->call('jawab');
        }

        $game->call('next')
            ->assertSet('finished', true)
            ->assertSee('Skor kamu: 4 dari 5.')
            ->assertSee('Kata yang meleset')
            ->assertSee($missed['hanzi'])
            ->assertSee('Main lagi');

        $game->call('restart')->assertSet('finished', false)->assertSet('score', 0)->assertCount('questions', 5);
    }

    public function test_double_answers_and_fake_options_are_rejected(): void
    {
        $this->seedWords();
        $game = $this->game();

        $game->set('pilihan', '9')->call('jawab')->assertSet('chosen', null)->assertHasErrors('pilihan');
        $game->set('pilihan', 'abc')->call('jawab')->assertSet('chosen', null);
        $game->call('next')->assertSet('index', 0); // can't skip without answering

        $correct = $this->correctIndex($game);
        $game->set('pilihan', (string) $correct)->call('jawab')
            ->set('pilihan', (string) $this->wrongIndex($game))->call('jawab');

        $game->assertSet('score', 1)->assertSet('chosen', $correct);
    }

    public function test_round_state_cannot_be_changed_from_the_browser(): void
    {
        $this->seedWords();

        foreach (['questions' => [], 'score' => 5, 'index' => 3, 'chosen' => 0, 'finished' => true, 'missed' => []] as $property => $value) {
            try {
                $this->game()->set($property, $value);
                $this->fail("{$property} should be locked");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_options_are_labelled_radios_in_a_fieldset_with_text_and_speech(): void
    {
        $this->seedWords();
        $game = $this->game();
        $question = $game->get('questions')[0];

        $game->assertSeeHtml('<fieldset')
            ->assertSeeHtml('<legend')
            ->assertSee('Apa arti kata ini?')
            ->assertSeeHtml('<label for="pilihan-0-0"')
            ->assertSeeHtml('type="radio" id="pilihan-0-0"')
            ->assertSeeHtml('lang="zh-Hans"')
            ->assertSee($question['hanzi'])
            ->assertSee($question['pinyin'])
            ->assertSee('Dengarkan lagi')
            ->assertSeeHtml('if (! window.screenReaderMode()) window.speak(')
            ->assertSeeHtml('<label for="kecepatan-suara"')
            ->assertSeeHtml('role="status"');
    }

    public function test_nothing_is_stored_and_no_network_calls(): void
    {
        $this->seedWords();
        $game = $this->game();
        $game->set('pilihan', (string) $this->correctIndex($game))->call('jawab');

        $this->assertSame(0, GameAttempt::count());
        Http::assertNothingSent();
    }

    public function test_not_enough_words_shows_a_status_message(): void
    {
        MandarinHskWord::create(['hanzi' => '我', 'pinyin' => 'wǒ', 'meaning_en' => 'I', 'meaning_id' => 'saya', 'hsk_level_old' => 1]);

        $this->game()->assertSet('questions', [])
            ->assertSee('Soal belum tersedia.')
            ->assertSeeHtml('<div role="status"');
    }

    public function test_catalog_entries_join_the_pool_as_their_own_level(): void
    {
        $path = sys_get_temp_dir().'/dengar-pilih-'.uniqid().'.json';
        file_put_contents($path, json_encode(array_map(fn ($e) => $e + ['ikut_prompt' => true, 'inggris' => '', 'ipa_inggris' => ''], [
            ['id' => 'a', 'hanzi' => '杯子', 'pinyin' => 'bēizi', 'nama_untuk_prompt' => 'gelas'],
            ['id' => 'b', 'hanzi' => '勺子', 'pinyin' => 'sháozi', 'nama_untuk_prompt' => 'sendok'],
            ['id' => 'c', 'hanzi' => '椅子', 'pinyin' => 'yǐzi', 'nama_untuk_prompt' => 'kursi'],
            ['id' => 'd', 'hanzi' => '桌子', 'pinyin' => 'zhuōzi', 'nama_untuk_prompt' => 'meja'],
        ]), JSON_UNESCAPED_UNICODE));
        config(['katabicara.path' => $path]);

        try {
            $questions = $this->game()->get('questions');
            $this->assertCount(4, $questions); // fewer words than a round: all of them
            $this->assertEqualsCanonicalizing(['gelas', 'sendok', 'kursi', 'meja'], $questions[0]['options']);
        } finally {
            @unlink($path);
        }
    }

    public function test_hub_links_to_the_game(): void
    {
        $this->seedWords();

        $this->actingAs($this->user())->get('/game')
            ->assertSee('Dengar &amp; Pilih', false)
            ->assertSee('Mulai Dengar &amp; Pilih', false)
            ->assertSee(route('game.listen-pick'), false);
        $this->actingAs($this->user())->get('/game/dengar-pilih')->assertOk();
    }
}
