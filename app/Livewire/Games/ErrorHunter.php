<?php

namespace App\Livewire\Games;

use App\Enums\ErrorCategory;
use App\Models\GameAttempt;
use App\Support\ErrorGameItems;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Pemburu Kesalahan": the learner sees a wrong Mandarin sentence, picks
 * which kind of mistake it is, then sees the correction and explanation. No
 * API calls — questions come from ErrorGameItems.
 *
 * A round's questions and each question's options are drawn once, when the
 * round starts, and kept in public state, so they never reshuffle between
 * requests. All round state is #[Locked]: the browser can call answer()/
 * next()/restart(), but can never set a score, swap options or skip ahead
 * (answers are also stored). Distractors only come from categories that
 * actually have questions (nada/pelafalan have no text questions and would
 * be obviously wrong), never 'lainnya' unless it's the answer, and a small
 * pool means fewer options rather than irrelevant ones.
 */
#[Layout('layouts.study')]
class ErrorHunter extends Component
{
    public const ROUND_SIZE = 10;

    public const MAX_OPTIONS = 4;

    /** @var array<int, array{id: int, options: string[]}> */
    #[Locked]
    public array $questions = [];

    #[Locked]
    public int $index = 0;

    /** The category picked for the current question; null = not answered yet. */
    #[Locked]
    public ?string $chosen = null;

    #[Locked]
    public int $score = 0;

    #[Locked]
    public int $streak = 0;

    #[Locked]
    public int $bestStreak = 0;

    /** @var int[] item ids answered wrong this round, in order */
    #[Locked]
    public array $missed = [];

    #[Locked]
    public bool $finished = false;

    public function mount(): void
    {
        $this->startRound();
    }

    public function restart(): void
    {
        $this->startRound();
    }

    private function startRound(): void
    {
        $items = ErrorGameItems::all();
        $pool = ErrorGameItems::categories();

        $ids = array_keys($items);
        shuffle($ids);

        $this->questions = array_map(function (int $id) use ($items, $pool) {
            $correct = $items[$id]['kategori'];

            $distractors = array_values(array_diff($pool, [$correct, ErrorCategory::Lainnya->value]));
            shuffle($distractors);

            $options = array_merge([$correct], array_slice($distractors, 0, self::MAX_OPTIONS - 1));
            shuffle($options);

            return ['id' => $id, 'options' => $options];
        }, array_slice($ids, 0, self::ROUND_SIZE));

        $this->index = 0;
        $this->chosen = null;
        $this->score = 0;
        $this->streak = 0;
        $this->bestStreak = 0;
        $this->missed = [];
        $this->finished = false;
        unset($this->current);
    }

    #[Computed]
    public function current(): ?array
    {
        $question = $this->questions[$this->index] ?? null;

        return $question ? ErrorGameItems::find($question['id']) : null;
    }

    public function answer(string $kategori): void
    {
        $question = $this->questions[$this->index] ?? null;

        // Only one answer per question, and only one of its own options.
        if ($this->finished || $this->chosen !== null || ! $question || ! in_array($kategori, $question['options'], true)) {
            return;
        }

        $this->chosen = $kategori;
        $correct = $kategori === $this->current['kategori'];

        GameAttempt::create([
            'user_id' => Auth::id(),
            'item_id' => (string) $question['id'],
            'kategori' => $this->current['kategori'],
            'benar' => $correct,
        ]);

        if ($correct) {
            $this->score++;
            $this->streak++;
            $this->bestStreak = max($this->bestStreak, $this->streak);
        } else {
            $this->streak = 0;
            $this->missed[] = $question['id'];
        }
    }

    public function next(): void
    {
        if ($this->chosen === null) {
            return;
        }

        if ($this->index + 1 >= count($this->questions)) {
            $this->finished = true;

            return;
        }

        $this->index++;
        $this->chosen = null;
        unset($this->current);
    }

    /**
     * End screen: the category missed most often this round (ties go to the
     * one missed last) with its latest missed sentence as the example.
     *
     * @return array{label: string, jumlah: int, salah: string, benar: string}|null
     */
    #[Computed]
    public function weakest(): ?array
    {
        $byCategory = [];
        foreach ($this->missed as $order => $id) {
            $item = ErrorGameItems::find($id);
            if (! $item) {
                continue;
            }

            $byCategory[$item['kategori']] = [
                'jumlah' => ($byCategory[$item['kategori']]['jumlah'] ?? 0) + 1,
                'last' => $order,
                'item' => $item,
            ];
        }

        if ($byCategory === []) {
            return null;
        }

        uasort($byCategory, fn ($a, $b) => [$b['jumlah'], $b['last']] <=> [$a['jumlah'], $a['last']]);
        $top = reset($byCategory);

        return [
            'label' => ErrorCategory::fromLoose($top['item']['kategori'])->label(),
            'jumlah' => $top['jumlah'],
            'salah' => $top['item']['salah'],
            'benar' => $top['item']['benar'],
        ];
    }

    public function render()
    {
        return view('livewire.games.error-hunter');
    }
}
