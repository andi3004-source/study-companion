<?php

namespace App\Livewire\Conversation;

use App\Models\ConversationSession;
use App\Services\ConversationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.study')]
class Chat extends Component
{
    public ConversationSession $session;

    public string $newMessage = '';

    public ?string $errorMessage = null;

    public ?array $summary = null;

    public bool $autoPlay = true;

    public function mount(ConversationSession $session): void
    {
        abort_if($session->user_id !== Auth::id(), 403);

        $this->session = $session;
    }

    #[Computed]
    public function turns()
    {
        return $this->session->turns()->orderBy('created_at')->orderBy('id')->get();
    }

    #[Computed]
    public function scenario(): array
    {
        return app(ConversationService::class)->scenarioDisplay($this->session);
    }

    #[Computed]
    public function remainingToday(): int
    {
        return app(ConversationService::class)->remainingMessagesToday(Auth::user());
    }

    /**
     * Shared entry point for both the text form and voice input (spec:
     * reuse the same method so rate-limit/history/validation never diverge
     * between the two input paths). $voiceText, when given, is the STT
     * transcript exactly as heard — never edited or auto-corrected — so a
     * consistently-mistranscribed word is a visible signal of a
     * pronunciation the user should drill.
     */
    public function sendMessage(?string $voiceText = null): void
    {
        $this->errorMessage = null;

        if ($this->session->ended_at) {
            return;
        }

        $content = trim($voiceText ?? $this->newMessage);

        if ($content === '') {
            $this->errorMessage = 'Pesan tidak boleh kosong.';

            return;
        }

        if (mb_strlen($content) > 500) {
            $this->errorMessage = 'Pesan maksimal 500 karakter.';

            return;
        }

        $service = app(ConversationService::class);

        if ($service->remainingMessagesToday(Auth::user()) <= 0) {
            $this->errorMessage = 'Kuota harian habis, lanjut besok.';

            return;
        }

        try {
            $assistantTurn = $service->sendMessage($this->session, $content);
        } catch (RuntimeException $e) {
            report($e);
            $this->errorMessage = 'AI sedang tidak tersedia. Coba lagi nanti.';

            return;
        }

        $this->newMessage = '';
        $this->session->refresh();
        unset($this->turns, $this->remainingToday);
        $this->dispatch('message-sent');
        $this->dispatch('ai-replied', text: $assistantTurn->content, langCode: $this->scenario['target_lang_code']);
    }

    public function finishSession(): void
    {
        $this->summary = app(ConversationService::class)->endSession($this->session);
        $this->session->refresh();
    }

    public function render()
    {
        return view('livewire.conversation.chat', [
            'dailyLimit' => ConversationService::DAILY_MESSAGE_LIMIT,
        ]);
    }
}
