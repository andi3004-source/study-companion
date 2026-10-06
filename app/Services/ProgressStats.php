<?php

namespace App\Services;

use App\Enums\ErrorCategory;
use App\Models\ConversationSession;
use App\Models\GameAttempt;
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

    /**
     * "Kesalahan terbanyakmu": the user's most frequent error categories over
     * their Mandarin sessions analyzed in the last $days days (failed analyses,
     * stored as NULL, are skipped). Ties go to the category seen most
     * recently. 'contoh'/'koreksi' come from that category's newest mistake.
     *
     * @return array<int, array{kategori: string, label: string, jumlah: int, contoh: string, koreksi: string}>
     */
    public function topErrorCategories(User $user, int $days = 7, int $limit = 3): array
    {
        $sessions = ConversationSession::where('user_id', $user->id)
            ->where('lang', 'mandarin')
            ->whereNotNull('error_analysis')
            ->where('analyzed_at', '>=', now()->subDays($days))
            ->orderByDesc('analyzed_at')
            ->orderByDesc('id')
            ->get(['id', 'error_analysis', 'analyzed_at']);

        $categories = [];
        $seen = 0; // position in newest-first order, for the recency tie-break

        foreach ($sessions as $session) {
            foreach ($session->error_analysis['items'] ?? [] as $item) {
                $category = ErrorCategory::fromLoose($item['kategori'] ?? null);
                $seen++;

                // Newest-first, so the first item of a category is its latest example.
                $categories[$category->value] ??= [
                    'kategori' => $category->value,
                    'label' => $category->label(),
                    'jumlah' => 0,
                    'contoh' => (string) ($item['contoh'] ?? ''),
                    'koreksi' => (string) ($item['koreksi'] ?? ''),
                    'first_seen' => $seen,
                ];
                $categories[$category->value]['jumlah']++;
            }
        }

        usort($categories, fn ($a, $b) => [$b['jumlah'], $a['first_seen']] <=> [$a['jumlah'], $b['first_seen']]);

        return array_map(
            fn ($c) => array_diff_key($c, ['first_seen' => true]),
            array_slice($categories, 0, $limit)
        );
    }

    /**
     * "Pemburu Kesalahan": the question categories the user most often failed
     * to recognize in the last $days days (ties: most recently missed first).
     * Reads game_attempts only — never mixed with the conversation-based
     * topErrorCategories(), which counts mistakes the learner made.
     *
     * @return array<int, array{kategori: string, label: string, jumlah: int}>
     */
    public function gameWeakCategories(User $user, int $days = 30, int $limit = 3): array
    {
        return GameAttempt::where('user_id', $user->id)
            ->where('benar', false)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('kategori, COUNT(*) as jumlah, MAX(created_at) as last_missed_at')
            ->groupBy('kategori')
            ->orderByDesc('jumlah')
            ->orderByDesc('last_missed_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'kategori' => ErrorCategory::fromLoose($row->kategori)->value,
                'label' => ErrorCategory::fromLoose($row->kategori)->label(),
                'jumlah' => (int) $row->jumlah,
            ])
            ->all();
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
