<?php

namespace App\Livewire\Games;

use App\Support\ListenPickWords;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Dengar & Pilih": hear a Mandarin word (also shown as hanzi + pinyin),
 * pick its Indonesian meaning from four. No AI and no time limit.
 *
 * The round is drawn once and kept in #[Locked] state; the only thing the
 * browser can change is $pilihan (the selected radio), and jawab() checks
 * it against the locked options on the server. Nothing is stored:
 * game_attempts holds Pemburu Kesalahan error categories, which Progress
 * reads as mistake types, so word answers don't belong there.
 */
#[Layout('layouts.study')]
class ListenPick extends Component
{
    public const ROUND_SIZE = 5;

    /** @var array<int, array{hanzi: string, pinyin: string, arti: string, options: string[]}> */
    #[Locked]
    public array $questions = [];

    #[Locked]
    public int $index = 0;

    /** Index of the option answered for the current question; null = not answered yet. */
    #[Locked]
    public ?int $chosen = null;

    #[Locked]
    public int $score = 0;

    /** @var array<int, array{hanzi: string, pinyin: string, arti: string}> */
    #[Locked]
    public array $missed = [];

    #[Locked]
    public bool $finished = false;

    /** The radio the learner has selected (not yet submitted). */
    public ?string $pilihan = null;

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
        $byGroup = ListenPickWords::all()->groupBy('grup')
            ->filter(fn ($words) => $words->pluck('arti')->map(fn ($a) => mb_strtolower($a))->unique()->count() >= ListenPickWords::OPTIONS);

        $targets = $byGroup->flatten(1)->shuffle()->take(self::ROUND_SIZE);

        $this->questions = $targets->map(function (array $word) use ($byGroup) {
            $distractors = $byGroup[$word['grup']]
                ->reject(fn ($other) => mb_strtolower($other['arti']) === mb_strtolower($word['arti']))
                ->unique(fn ($other) => mb_strtolower($other['arti']))
                ->shuffle()
                ->take(ListenPickWords::OPTIONS - 1)
                ->pluck('arti');

            return [
                'hanzi' => $word['hanzi'],
                'pinyin' => $word['pinyin'],
                'arti' => $word['arti'],
                'options' => $distractors->push($word['arti'])->shuffle()->values()->all(),
            ];
        })->values()->all();

        $this->index = 0;
        $this->chosen = null;
        $this->score = 0;
        $this->missed = [];
        $this->finished = false;
        $this->pilihan = null;
    }

    public function jawab(): void
    {
        $question = $this->questions[$this->index] ?? null;

        // One answer per question, and only one of its own options.
        if ($this->finished || $this->chosen !== null || ! $question
            || ! is_numeric($this->pilihan) || ! array_key_exists((int) $this->pilihan, $question['options'])) {
            $this->addError('pilihan', 'Pilih salah satu arti dulu, lalu tekan Jawab.');

            return;
        }

        $this->chosen = (int) $this->pilihan;

        if ($question['options'][$this->chosen] === $question['arti']) {
            $this->score++;
        } else {
            $this->missed[] = ['hanzi' => $question['hanzi'], 'pinyin' => $question['pinyin'], 'arti' => $question['arti']];
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
        $this->pilihan = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.games.listen-pick')->title('Dengar & Pilih');
    }
}
