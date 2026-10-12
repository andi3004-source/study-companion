<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Chat;
use App\Livewire\Conversation\Picker;
use App\Livewire\Ujian\Runner;
use App\Models\ConversationSession;
use App\Models\User;
use App\Support\AiErrorMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(): User
    {
        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    protected function chatSession(User $user, string $lang = 'english'): ConversationSession
    {
        return ConversationSession::create([
            'user_id' => $user->id, 'lang' => $lang,
            'scenario_key' => $lang === 'english' ? 'job_interview' : array_key_first(config('scenarios.mandarin')),
            'started_at' => now(),
        ]);
    }

    public function test_rate_limited_reply_shows_busy_message_and_keeps_typed_text(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Chat::class, ['session' => $this->chatSession($user)])
            ->set('newMessage', 'Hello there')
            ->call('sendMessage')
            ->assertOk()
            ->assertSet('errorMessage', AiErrorMessage::BUSY)
            ->assertSet('newMessage', 'Hello there')
            ->assertSee('Layanan AI sedang penuh, coba lagi sebentar.');
    }

    public function test_high_demand_message_is_treated_as_busy(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'The model is overloaded due to high demand.']], 500)]);
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Chat::class, ['session' => $this->chatSession($user)])
            ->set('newMessage', 'Hi')
            ->call('sendMessage')
            ->assertSet('errorMessage', AiErrorMessage::BUSY);
    }

    public function test_timeout_shows_timeout_message(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Chat::class, ['session' => $this->chatSession($user)])
            ->set('newMessage', 'Hi')
            ->call('sendMessage')
            ->assertOk()
            ->assertSet('errorMessage', AiErrorMessage::TIMEOUT)
            ->assertSet('newMessage', 'Hi');
    }

    public function test_random_start_failure_shows_friendly_message_and_leaves_no_empty_session(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Picker::class)
            ->call('startRandom')
            ->assertOk()
            ->assertNoRedirect()
            ->assertSet('errorMessage', AiErrorMessage::BUSY)
            ->assertSee('Layanan AI sedang penuh, coba lagi sebentar.');

        $this->assertSame(0, ConversationSession::where('user_id', $user->id)->count());
    }

    public function test_picker_heading_is_indonesian(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Picker::class)
            ->assertSee('Teman Ngobrol AI')
            ->assertDontSee('AI Conversation Partner');
    }

    public function test_english_session_hint_does_not_mention_mandarin_or_pinyin(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Chat::class, ['session' => $this->chatSession($user)])
            ->assertSee('Tulis jawabanmu dalam bahasa Inggris.')
            ->assertDontSee('pinyin dengan angka nada');
    }

    public function test_exam_heading_uses_a_readable_label(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Runner::class, ['mode' => 'english'])
            ->assertSee('Ujian English');

        Livewire::actingAs($this->onboardedUser())->test(Runner::class, ['mode' => 'today'])
            ->assertSee('Tes Hari Ini')
            ->assertDontSee('Ujian today');
    }
}
