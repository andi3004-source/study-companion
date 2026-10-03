<?php

namespace App\Livewire\Conversation;

use App\Services\ConversationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.study')]
class Picker extends Component
{
    public string $lang = 'mandarin';

    #[Computed]
    public function scenarios(): array
    {
        return app(ConversationService::class)->scenarios($this->lang);
    }

    public function selectScenario(string $scenarioKey)
    {
        $session = app(ConversationService::class)->startSession(Auth::user(), $this->lang, $scenarioKey);

        return $this->redirect(route('conversation.chat', $session));
    }

    public function startRandom()
    {
        $session = app(ConversationService::class)->startRandomSession(Auth::user(), $this->lang);

        return $this->redirect(route('conversation.chat', $session));
    }

    public function render()
    {
        return view('livewire.conversation.picker');
    }
}
