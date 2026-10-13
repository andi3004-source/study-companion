<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Chat;
use App\Models\ConversationApiCall;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\GeminiSessionAnalyzer;
use App\Support\AiErrorMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Concerns\UsesScanCatalog;
use Tests\TestCase;

/**
 * Retries happen inside the Gemini client, below ConversationService: one
 * message is one user turn however many HTTP attempts it takes, and a call
 * that fails on every attempt still leaves no turn behind.
 */
class GeminiRetryQuotaPrivacyTest extends TestCase
{
    use RefreshDatabase;
    use UsesScanCatalog;

    private const KEY = 'kunci-rahasia-uji';

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.gemini.key' => self::KEY]);
    }

    private function ok(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]], 'usageMetadata' => ['promptTokenCount' => 3, 'candidatesTokenCount' => 2]];
    }

    private function onboardedUser(): User
    {
        return User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    private function englishSession(User $user): ConversationSession
    {
        return ConversationSession::create(['user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now()]);
    }

    public function test_all_attempts_failing_uses_no_quota_and_leaves_no_turn(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);
        $user = $this->onboardedUser();
        $session = $this->englishSession($user);
        $service = app(ConversationService::class);
        $before = $service->remainingMessagesToday($user);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello?')
            ->call('sendMessage')
            ->assertSet('errorMessage', AiErrorMessage::BUSY)
            ->assertSet('newMessage', 'Hello?');

        Http::assertSentCount(3);
        $this->assertSame($before, $service->remainingMessagesToday($user));
        $this->assertSame(0, ConversationTurn::where('session_id', $session->id)->count());
        $this->assertSame(0, ConversationApiCall::count());
    }

    public function test_success_after_a_retry_uses_one_quota_and_stores_one_user_turn(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push([], 503)
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->push($this->ok('Nice to meet you.'))]);
        $user = $this->onboardedUser();
        $session = $this->englishSession($user);
        $service = app(ConversationService::class);
        $before = $service->remainingMessagesToday($user);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello?')
            ->call('sendMessage')
            ->assertSet('errorMessage', null);

        Http::assertSentCount(3);
        $this->assertSame($before - 1, $service->remainingMessagesToday($user));
        $turns = ConversationTurn::where('session_id', $session->id)->orderBy('id')->get();
        $this->assertSame(['user', 'assistant'], $turns->pluck('role')->all());
        $this->assertSame('Hello?', $turns[0]->content);
        $this->assertSame(1, ConversationApiCall::count());
    }

    public function test_the_gemini_error_analyzer_is_retried_too(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push([], 503)
            ->push($this->ok('{"items":[],"catatan":"Bagus."}'))]);

        $result = app(GeminiSessionAnalyzer::class)->analyze("AI: 你好！\nUser: 你好");

        $this->assertSame('Bagus.', $result['catatan']);
        $this->assertSame('gemini', $result['sumber']);
        Http::assertSentCount(2);
    }

    public function test_scan_retries_keep_the_photo_out_of_storage_and_logs(): void
    {
        Log::spy();
        $this->useScanCatalog();
        config(['scan.enabled' => true]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);
        $before = collect(File::allFiles(storage_path()))->map->getPathname()->all();

        try {
            $this->actingAs($this->onboardedUser())
                ->postJson(route('scan.recognize'), ['consent' => true, 'image' => self::PNG_BASE64])
                ->assertStatus(503)
                ->assertJsonPath('pesan', AiErrorMessage::BUSY);
        } finally {
            $this->removeScanCatalog();
        }

        Http::assertSentCount(3);
        $new = array_diff(collect(File::allFiles(storage_path()))->map->getPathname()->all(), $before);
        $this->assertSame([], array_values($new));

        // Three attempt warnings and the controller's own one; none carries the image or the key.
        Log::shouldHaveReceived('warning')->times(4);
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            $logged = $message.json_encode($context);

            return ! str_contains($logged, self::PNG_BASE64) && ! str_contains($logged, self::KEY);
        })->times(4);
    }
}
