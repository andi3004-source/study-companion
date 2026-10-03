<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Jobs\TranslateEnglishVocabJob;
use App\Jobs\TranslateMandarinMeaningsJob;
use App\Models\EnglishVocab;
use App\Models\MandarinHskWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

            public function __construct(private bool $configured) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                $this->calls++;
                $words = json_decode($messages[0]['content'], true);

                return [
                    'text' => json_encode(array_map(fn ($w) => strtoupper($w).'_ID', $words)),
                    'tokens_in' => 1,
                    'tokens_out' => 1,
                ];
            }
        };
    }

    private function mandarinWord(?string $meaningId = null): MandarinHskWord
    {
        return MandarinHskWord::create([
            'hanzi' => '测试',
            'pinyin' => 'cè shì '.uniqid(),
            'meaning_en' => 'test',
            'meaning_id' => $meaningId,
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
