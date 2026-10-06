<?php

namespace App\Livewire;

use App\Models\PlatformLink;
use App\Services\ProgressStats;
use App\Services\Scheduler;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.study')]
class ProgressOverview extends Component
{
    public function render()
    {
        $user = Auth::user();
        $day = max(1, $user->dayNumber() ?? 1);
        $stats = app(ProgressStats::class);
        $scheduler = app(Scheduler::class);

        return view('livewire.progress-overview', [
            'day' => $day,
            'mandarinDeck' => $stats->deckProgress($user, 'mandarin'),
            'verbDeck' => $stats->deckProgress($user, 'verb'),
            'vocabDeck' => $stats->deckProgress($user, 'vocab'),
            'streak' => $stats->streak($user, $day),
            'totalTasks' => $stats->totalTasksCompleted($user),
            'mandarinStatus' => $scheduler->mandarinStatus($day),
            'englishStatus' => $scheduler->englishStatus($day),
            'platformLinks' => PlatformLink::orderBy('name')->get(),
            'topErrors' => $stats->topErrorCategories($user),
        ]);
    }
}
