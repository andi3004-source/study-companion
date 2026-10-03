<?php

namespace App\Livewire\Ujian;

use App\Models\UserExamScore;
use App\Services\Scheduler;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.study')]
class Picker extends Component
{
    public int $day;

    public function mount(): void
    {
        $this->day = max(1, Auth::user()->dayNumber() ?? 1);
    }

    public function getHasTodayVocabProperty(): bool
    {
        $items = app(Scheduler::class)->planForDay($this->day, Auth::user()->track);

        return collect($items)->contains(fn ($i) => $i['type'] === 'vocab');
    }

    public function getRecentScoresProperty()
    {
        return UserExamScore::where('user_id', Auth::id())->latest('taken_at')->limit(5)->get();
    }

    public function render()
    {
        return view('livewire.ujian.picker');
    }
}
