<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.study')]
class Onboarding extends Component
{
    public string $start_date = '';

    public string $track = 'both';

    public function mount(): void
    {
        $this->start_date = now()->toDateString();
    }

    public function save(): void
    {
        $this->validate([
            'start_date' => ['required', 'date'],
            'track' => ['required', 'in:mandarin,english,both'],
        ]);

        $user = Auth::user();
        $user->update([
            'start_date' => $this->start_date,
            'track' => $this->track,
            'onboarded' => true,
        ]);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding');
    }
}
