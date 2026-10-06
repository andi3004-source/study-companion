<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Jobs\TranslateEnglishVocabJob;
use App\Jobs\TranslateMandarinMeaningsJob;
use App\Models\EnglishVocab;
use App\Models\MandarinHskWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class TranslateMeaningsJobTest extends TestCase
{
    use RefreshDatabase;

    /** A fake AiClient that "translates" by uppercasing, so assertions don't depend on real Gemini output. */
    private function fakeClient(bool $configured = true): AiClient
    {
        return new class($configured) implements AiClient
        {
            public int $calls = 0;

            public ?string $system = null;

            public function __construct(private bool $configured) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                $this->calls++;
                $this->system = $system;
                $words = json_decode($messages[0]['content'], true);

                return [
                    'text' => json_encode(array_map(fn ($w) => strtoupper($w).'_ID', $words)),
                    'tokens_in' => 1,
                    'tokens_out' => 1,
                ];
            }
        };
    }

    private function mandarinWord(?string $meaningId = null, string $meaningEn = 'test', ?string $dataStatus = null): MandarinHskWord
    {
        return MandarinHskWord::create([
            'hanzi' => '测试',
            'pinyin' => 'cè shì '.uniqid(),
            'meaning_en' => $meaningEn,
            'meaning_id' => $meaningId,
            'data_status' => $dataStatus,
            'hsk_level_old' => 1,
        ]);
    }

    private function englishWord(array $translations = []): EnglishVocab
    {
        static $externalId = 1;

        return EnglishVocab::create([
            'external_id' => $externalId++,
            'word' => 'cat',
            'cefr_level' => 'A1',
            'translations' => $translations,
        ]);
    }

    public function test_mandarin_job_fills_only_rows_missing_meaning_id(): void
    {
        $untranslated = $this->mandarinWord(null);
        $alreadyDone = $this->mandarinWord('sudah ada terjemahan manual');

        $client = $this->fakeClient();
        $this->app->instance(AiClient::class, $client);

        TranslateMandarinMeaningsJob::dispatchSync();

        $this->assertSame('TEST_ID', $untranslated->fresh()->meaning_id);
        // Manual/pre-existing translation must never be overwritten.
        $this->assertSame('sudah ada terjemahan manual', $alreadyDone->fresh()->meaning_id);
    }

    public function test_mandarin_job_skips_hand_corrected_rows(): void
    {
        $corrected = $this->mandarinWord(null, 'surname Ye', 'corrected_draft');
        $normal = $this->mandarinWord(null);

        $client = $this->fakeClient();
        $this->app->instance(AiClient::class, $client);

        TranslateMandarinMeaningsJob::dispatchSync();

        $this->assertNull($corrected->fresh()->meaning_id);
        $this->assertSame('TEST_ID', $normal->fresh()->meaning_id);
    }

    public function test_mandarin_job_leaves_surname_and_slang_glosses_untranslated(): void
    {
        // Wrong-homograph glosses from the dataset (都 "surname Du"): a
        // translation would just be a wrong meaning, so they stay NULL and
        // the UI shows "Arti belum tersedia" until corrected by hand.
        $surname = $this->mandarinWord(null, 'surname Du');
        $slang = $this->mandarinWord(null, 'airport; airfield; (slang) service provider');
        $normal = $this->mandarinWord(null, 'to listen');

        $client = $this->fakeClient();
        $this->app->instance(AiClient::class, $client);

        TranslateMandarinMeaningsJob::dispatchSync();

        $this->assertNull($surname->fresh()->meaning_id);
        $this->assertNull($slang->fresh()->meaning_id);
        $this->assertSame('TO LISTEN_ID', $normal->fresh()->meaning_id);
    }

    public function test_translation_prompt_warns_about_wrong_homograph_glosses(): void
    {
        $this->mandarinWord(null);

        $client = $this->fakeClient();
        $this->app->instance(AiClient::class, $client);

        TranslateMandarinMeaningsJob::dispatchSync();

        $this->assertStringContainsString('homograf', $client->system);
        $this->assertStringContainsString('marga', $client->system);
    }

    public function test_command_counts_and_queues_nothing_when_only_skipped_rows_remain(): void
    {
        Queue::fake();
        $this->mandarinWord(null, 'surname Du');
        $this->mandarinWord(null, 'surname Ye', 'corrected_draft');
        $this->mandarinWord('sudah ada');

        $this->artisan('translate:meanings', ['--mandarin' => true])
            ->expectsOutputToContain('Mandarin HSK: 0 row(s) to translate')
            ->assertSuccessful();

        Queue::assertNotPushed(TranslateMandarinMeaningsJob::class);
    }

    public function test_mandarin_job_skips_the_api_entirely_when_not_configured(): void
    {
        $word = $this->mandarinWord(null);

        $client = $this->fakeClient(configured: false);
        $this->app->instance(AiClient::class, $client);

        TranslateMandarinMeaningsJob::dispatchSync();

        $this->assertNull($word->fresh()->meaning_id);
    }

    public function test_mandarin_job_is_resumable_a_second_run_only_processes_what_is_still_missing(): void
    {
        $this->mandarinWord('sudah ada'); // already translated, untouched by either run
        $stillMissing = $this->mandarinWord(null);

        $client = $this->fakeClient();
        $this->app->instance(AiClient::class, $client);

        TranslateMandarinMeaningsJob::dispatchSync();
        $this->assertSame(1, $client->calls);
        $this->assertSame('TEST_ID', $stillMissing->fresh()->meaning_id);

        // Nothing left to translate: a second run (e.g. after a re-dispatch
        // following an earlier failure) must not call the API again at all.
        TranslateMandarinMeaningsJob::dispatchSync();
        $this->assertSame(1, $client->calls);
    }

    public function test_mandarin_job_leaves_the_row_untouched_if_the_api_call_throws(): void
    {
        $word = $this->mandarinWord(null);

        $failingClient = new class implements AiClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                throw new RuntimeException('simulated transient API failure');
            }
        };
        $this->app->instance(AiClient::class, $failingClient);

        try {
            TranslateMandarinMeaningsJob::dispatchSync();
            $this->fail('expected the job to propagate the failure');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull($word->fresh()->meaning_id);
    }

    public function test_english_job_fills_only_rows_missing_the_id_translation_key(): void
    {
        $untranslated = $this->englishWord(['en' => 'cat']);
        $alreadyDone = $this->englishWord(['id' => 'sudah ada']);

        $client = $this->fakeClient();
        $this->app->instance(AiClient::class, $client);

        TranslateEnglishVocabJob::dispatchSync();

        $this->assertSame('CAT_ID', $untranslated->fresh()->translations['id']);
        $this->assertSame('sudah ada', $alreadyDone->fresh()->translations['id']);
    }
}
