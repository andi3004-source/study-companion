<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Livewire\Conversation\Chat;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ConversationQuotaOnFailureTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    private function englishSession(User $user): ConversationSession
    {
        return ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);
    }

    private function fakeClient(?string $reply): void
    {
        $this->app->instance(AiClient::class, new class($reply) implements AiClient
        {
            public function __construct(private ?string $reply) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                if ($this->reply === null) {
                    throw new RuntimeException('429 high demand');
                }

                return ['text' => $this->reply, 'tokens_in' => 1, 'tokens_out' => 1];
            }
        });
    }

    public function test_failed_ai_call_leaves_no_user_turn_and_rethrows(): void
    {
        $this->fakeClient(null);
        $user = $this->onboardedUser();
        $session = $this->englishSession($user);
        $service = app(ConversationService::class);

        try {
            $service->sendMessage($session, 'Hello?');
            $this->fail('The AI error should be rethrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('429 high demand', $e->getMessage());
        }

        $this->assertSame(0, ConversationTurn::where('session_id', $session->id)->count());
    }

    public function test_failed_ai_call_does_not_change_the_daily_quota(): void
    {
        $this->fakeClient(null);
        $user = $this->onboardedUser();
        $session = $this->englishSession($user);
        $service = app(ConversationService::class);
        $before = $service->remainingMessagesToday($user);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello?')
            ->call('sendMessage')
            ->assertOk()
            ->assertSet('newMessage', 'Hello?')
            ->assertSet('errorMessage', 'Layanan AI sedang penuh, coba lagi sebentar.');

        $this->assertSame($before, $service->remainingMessagesToday($user));
    }

    public function test_success_path_still_stores_both_turns_and_uses_one_quota(): void
    {
        $this->fakeClient('Nice to meet you.');
        $user = $this->onboardedUser();
        $session = $this->englishSession($user);
        $service = app(ConversationService::class);
        $before = $service->remainingMessagesToday($user);

        $assistant = $service->sendMessage($session, 'Hello?');

        $turns = ConversationTurn::where('session_id', $session->id)->orderBy('id')->get();
        $this->assertSame(['user', 'assistant'], $turns->pluck('role')->all());
        $this->assertSame('Hello?', $turns[0]->content);
        $this->assertSame('Nice to meet you.', $assistant->content);
        $this->assertSame($before - 1, $service->remainingMessagesToday($user));
    }
}
