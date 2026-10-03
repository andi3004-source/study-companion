<?php

namespace App\Services;

use App\Models\IeltsVocab;
use App\Models\IrregularVerb;
use App\Models\MandarinWord;
use App\Models\User;
use App\Models\UserChecklist;
use App\Models\UserWordProgress;

class ProgressStats
{
    public function __construct(private Scheduler $scheduler) {}

    /** Consecutive fully-completed days ending at (and including) $currentDay, counting backward. */
    public function streak(User $user, int $currentDay): int
    {
        $streak = 0;

        for ($d = $currentDay; $d >= 1; $d--) {
            $items = $this->scheduler->planForDay($d, $user->track);
            if (empty($items)) {
                continue;
            }

            $checkedCount = UserChecklist::where('user_id', $user->id)
                ->where('day_number', $d)
                ->where('is_checked', true)
                ->count();

            if ($checkedCount >= count($items)) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    public function totalTasksCompleted(User $user): int
    {
        return UserChecklist::where('user_id', $user->id)->where('is_checked', true)->count();
    }

    /** ['known' => int, 'total' => int, 'percent' => int] for a word deck. */
    public function deckProgress(User $user, string $wordType): array
    {
        $total = match ($wordType) {
            'mandarin' => MandarinWord::count(),
            'verb' => IrregularVerb::count(),
            'vocab' => IeltsVocab::count(),
            default => 0,
        };

        $known = UserWordProgress::where('user_id', $user->id)
            ->where('word_type', $wordType)
            ->where('is_known', true)
            ->count();

        return [
            'known' => $known,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($known / $total * 100) : 0,
        ];
    }

    public function mandarinDaysRemaining(int $day): int
    {
        return max(0, Scheduler::MANDARIN_TOTAL_DAYS - $day);
    }

    public function englishDaysRemaining(int $day): int
    {
        return max(0, Scheduler::ENGLISH_TOTAL_DAYS - $day);
    }
}
