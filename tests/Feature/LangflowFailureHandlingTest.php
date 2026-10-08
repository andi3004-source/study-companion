<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Contracts\SessionAnalyzer;
use App\Exceptions\LangflowRequestException;
use App\Jobs\AnalyzeSessionJob;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Services\LangflowSessionAnalyzer;
use App\Support\SecretRedactor;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/** Non-2xx Langflow responses: redacted error messages, transient retries in AnalyzeSessionJob, and the Gemini fallback. */
class LangflowFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    private const RUN_URL = 'https://langflow.test/api/v1/run/flow-123?stream=false';

    // Fake, but shaped like real keys so both the literal and the pattern redaction are exercised.
    private const API_KEY = 'lf-FAKE-key-0123456789abcdef';

    private const LANGFLOW_JSON = '{"items":[{"kategori":"bu_atau_mei","contoh":"我不有钱","koreksi":"我没有钱","penjelasan":"..."}],"catatan":"Dari Langflow."}';

    protected function setUp(): void
    {
        parent::setUp();

        Exceptions::fake();
        config(['services.langflow' => ['url' => 'https://langflow.test', 'flow_id' => 'flow-123', 'api_key' => self::API_KEY, 'timeout' => 120]]);
    }

    /** Fake Gemini (AiClient); throws when $text is null. */
    private function fakeGemini(?string $text = '{"items":[],"catatan":"Dari Gemini."}'): object
    {
        $client = new class($text) implements AiClient
        {
            public int $calls = 0;

            public function __construct(private ?string $text) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                $this->calls++;

                return ['text' => $this->text ?? throw new RuntimeException('simulated Gemini failure')];
            }
        };

        $this->app->instance(AiClient::class, $client);

        return $client;
    }

    private function runResponse(string $text = self::LANGFLOW_JSON): array
    {
        return ['outputs' => [['outputs' => [['results' => ['message' => ['text' => $text]]]]]]];
    }

    private function mandarinSession(): ConversationSession
    {
        $session = ConversationSession::create(['user_id' => User::factory()->create()->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering', 'started_at' => now(), 'ended_at' => now()]);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => '我不有钱', 'input_mode' => 'text']);

        return $session;
    }

    /** Runs the job as a real queue worker would on its $attempt-th try; returns the queue job spy. */
    private function runOnWorker(ConversationSession $session, int $attempt): MockInterface
    {
        $queueJob = Mockery::spy(QueueJob::class);
        $queueJob->shouldReceive('attempts')->andReturn($attempt);

        $job = new AnalyzeSessionJob($session->id);
        $job->setJob($queueJob);
        $job->handle(app(SessionAnalyzer::class));

        return $queueJob;
    }

    private function langflowError(): LangflowRequestException
    {
        try {
            app(LangflowSessionAnalyzer::class)->analyze('User: 你好');
        } catch (LangflowRequestException $e) {
            return $e;
        }

        $this->fail('expected a LangflowRequestException');
    }

    // --- error messages ---

    public function test_a_500_quota_error_is_permanent_and_carries_status_and_body(): void
    {
        Http::fake([self::RUN_URL => Http::response(['detail' => 'Error running graph: You exceeded your current quota, please check your plan and billing details.'], 500)]);

        $e = $this->langflowError();

        $this->assertSame(500, $e->status);
        $this->assertFalse($e->transient);
        $this->assertStringStartsWith('Langflow HTTP 500: ', $e->getMessage());
        $this->assertStringContainsString('You exceeded your current quota', $e->getMessage());
    }

    public function test_the_error_message_is_cut_to_500_characters_of_body(): void
    {
        Http::fake([self::RUN_URL => Http::response(str_repeat('x', 2000), 500)]);

        $this->assertSame('Langflow HTTP 500: '.str_repeat('x', 500), $this->langflowError()->getMessage());
    }

    public function test_keys_in_the_response_body_are_redacted_from_the_error_message_and_report(): void
    {
        $this->fakeGemini();
        $leaks = [self::API_KEY, 'sk-FAKEopenai0123456789', 'AIzaFAKEgoogle0123456789abcd', 'AstraCS:FAKEclient:0123456789abcdef'];
        Http::fake([self::RUN_URL => Http::response(['detail' => 'Invalid credentials: '.implode(' | ', $leaks)], 401)]);

        $message = $this->langflowError()->getMessage();

        foreach ($leaks as $leak) {
            $this->assertStringNotContainsString($leak, $message);
        }
        $this->assertSame(4, substr_count($message, SecretRedactor::MASK));

        // The same exception is what FallbackSessionAnalyzer reports to the log.
        app(SessionAnalyzer::class)->analyze('User: 你好');
        Exceptions::assertReported(fn (LangflowRequestException $e) => ! str_contains($e->getMessage(), self::API_KEY)
            && ! str_contains($e->getMessage(), 'sk-FAKE'));
    }

    public function test_redaction_happens_before_truncation(): void
    {
        $text = str_repeat('x', 490).self::API_KEY;

        $this->assertSame(str_repeat('x', 490).SecretRedactor::MASK, SecretRedactor::snippet($text));
        $this->assertStringNotContainsString('lf-FAKE', SecretRedactor::snippet($text, 495));
    }

    public function test_redaction_leaves_ordinary_text_alone(): void
    {
        config(['services.langflow.api_key' => 'abc']); // too short to replace literally

        $this->assertSame('task-force abc 你好 AIza', SecretRedactor::redact('task-force abc 你好 AIza'));
    }

    public function test_a_response_without_text_is_logged_redacted(): void
    {
        Http::fake([self::RUN_URL => Http::response(['outputs' => [], 'echo' => self::API_KEY])]);
        Log::spy();

        try {
            app(LangflowSessionAnalyzer::class)->analyze('User: 你好');
        } catch (RuntimeException) {
        }

        Log::shouldHaveReceived('debug')->once()->withArgs(fn ($message, $context) => ! str_contains($context['body'], self::API_KEY)
            && str_contains($context['body'], SecretRedactor::MASK));
    }

    public function test_transient_failures_are_recognised(): void
    {
        foreach ([429, 502, 503, 504] as $status) {
            $this->assertTrue(LangflowRequestException::isTransient($status, ''), "HTTP {$status}");
        }
        $this->assertTrue(LangflowRequestException::isTransient(500, '{"detail":"The model is currently experiencing high demand"}'));
        $this->assertTrue(LangflowRequestException::isTransient(500, 'Model OVERLOADED, try again later'));

        foreach ([400, 401, 403, 404, 422, 500] as $status) {
            $this->assertFalse(LangflowRequestException::isTransient($status, 'quota exceeded'), "HTTP {$status}");
        }
    }

    // --- retries in AnalyzeSessionJob (real queue worker) ---

    public function test_a_503_is_retried_and_recovers_on_the_second_attempt(): void
    {
        $gemini = $this->fakeGemini();
        Http::fake([self::RUN_URL => Http::sequence()
            ->push('Service Unavailable', 503)
            ->push($this->runResponse())]);
        $session = $this->mandarinSession();

        $first = $this->runOnWorker($session, attempt: 1);

        $first->shouldHaveReceived('release')->once()->with(15);
        $this->assertNull($session->fresh()->analyzed_at); // still pending, not "failed"
        $this->assertSame(0, $gemini->calls);
        Exceptions::assertReported(fn (LangflowRequestException $e) => $e->status === 503);

        $second = $this->runOnWorker($session, attempt: 2);

        $second->shouldNotHaveReceived('release');
        $this->assertSame('Dari Langflow.', $session->fresh()->error_analysis['catatan']);
        $this->assertSame(0, $gemini->calls);
        Http::assertSentCount(2);
    }

    public function test_the_second_retry_waits_longer(): void
    {
        $this->fakeGemini();
        Http::fake([self::RUN_URL => Http::response('Bad Gateway', 502)]);

        $this->runOnWorker($this->mandarinSession(), attempt: 2)->shouldHaveReceived('release')->once()->with(45);
    }

    public function test_a_401_is_not_retried_and_falls_back_to_gemini(): void
    {
        $gemini = $this->fakeGemini();
        Http::fake([self::RUN_URL => Http::response(['detail' => 'Invalid API key'], 401)]);
        $session = $this->mandarinSession();

        $this->runOnWorker($session, attempt: 1)->shouldNotHaveReceived('release');

        $this->assertSame('Dari Gemini.', $session->fresh()->error_analysis['catatan']);
        $this->assertSame(1, $gemini->calls);
        Http::assertSentCount(1);
        Exceptions::assertReported(fn (LangflowRequestException $e) => $e->status === 401 && ! $e->transient);
    }

    public function test_broken_json_is_not_retried(): void
    {
        $gemini = $this->fakeGemini();
        Http::fake([self::RUN_URL => Http::response($this->runResponse('```json {"items": ['))]);
        $session = $this->mandarinSession();

        $this->runOnWorker($session, attempt: 1)->shouldNotHaveReceived('release');

        $this->assertSame(1, $gemini->calls);
        $this->assertNotNull($session->fresh()->analyzed_at);
    }

    public function test_after_the_last_attempt_a_transient_failure_falls_back_to_gemini(): void
    {
        $gemini = $this->fakeGemini();
        Http::fake([self::RUN_URL => Http::response('{"detail":"model overloaded"}', 500)]);
        $session = $this->mandarinSession();

        $this->runOnWorker($session, attempt: 3)->shouldNotHaveReceived('release');

        $this->assertSame('Dari Gemini.', $session->fresh()->error_analysis['catatan']);
        $this->assertSame(1, $gemini->calls);
    }

    public function test_when_gemini_also_fails_the_analysis_is_marked_failed_for_retry_failed(): void
    {
        $this->fakeGemini(null);
        Http::fake([self::RUN_URL => Http::response('Service Unavailable', 503)]);
        $session = $this->mandarinSession();

        $this->runOnWorker($session, attempt: 3);

        $session->refresh();
        $this->assertNotNull($session->analyzed_at);
        $this->assertNull($session->error_analysis);
        $this->artisan('analysis:retry-failed', ['--dry-run' => true])
            ->expectsOutputToContain('1 session(s) would be re-queued.');
    }

    public function test_a_job_the_worker_gives_up_on_is_marked_failed(): void
    {
        $session = $this->mandarinSession();
        $done = $this->mandarinSession();
        $done->update(['analyzed_at' => now(), 'error_analysis' => ['items' => [], 'catatan' => null]]);

        (new AnalyzeSessionJob($session->id))->failed(new RuntimeException('timed out'));
        (new AnalyzeSessionJob($done->id))->failed(new RuntimeException('late duplicate'));

        $this->assertNotNull($session->fresh()->analyzed_at);
        $this->assertNull($session->fresh()->error_analysis);
        $this->assertSame([], $done->fresh()->error_analysis['items']); // a finished analysis is never overwritten
    }

    // --- sync queue / dispatchAfterResponse: no retry, never left hanging ---

    public function test_on_the_sync_queue_a_transient_failure_goes_straight_to_gemini(): void
    {
        $gemini = $this->fakeGemini();
        Http::fake([self::RUN_URL => Http::response('Service Unavailable', 503)]);
        $session = $this->mandarinSession();

        AnalyzeSessionJob::dispatchSync($session->id);

        $this->assertSame('Dari Gemini.', $session->fresh()->error_analysis['catatan']);
        $this->assertSame(1, $gemini->calls);
        Http::assertSentCount(1);
    }

    public function test_after_response_dispatch_never_leaves_the_session_hanging(): void
    {
        $this->fakeGemini(null);
        Http::fake([self::RUN_URL => Http::response('Too Many Requests', 429)]);
        $session = $this->mandarinSession();

        AnalyzeSessionJob::dispatchAfterResponse($session->id);
        $this->app->terminate(); // runs the after-response callbacks

        $session->refresh();
        $this->assertNotNull($session->analyzed_at);
        $this->assertNull($session->error_analysis);
        Http::assertSentCount(1);
    }
}
