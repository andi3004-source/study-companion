<?php

namespace App\Livewire;

use App\Models\MandarinWord;
use App\Services\AnswerChecker;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fill-in-the-blank recall practice for Mandarin vocab. Each mode shows exactly
 * one word attribute (hanzi/pinyin/arti) as the clue and asks for the other two,
 * which is why the answer checkers cover all three attribute types even though
 * no single mode uses all three as answers at once.
 */
#[Layout('layouts.study')]
class LatihanIsian extends Component
{
    public string $modeSoal = 'hanzi_from_pinyin';

    public string $jumlah = '10';

    public string $urutan = 'sequential';

    /**
     * Set via ?batchFrom=&batchTo= from the dashboard checklist's "Mulai
     * Latihan Isian" button on a review/quiz item, scoping the pool to that
     * week's batches instead of all 150 words. Both null (the default, or
     * the "review total HSK1" item which intentionally omits them) means
     * the full pool, same as before this existed.
     */
    #[Url]
    public ?int $batchFrom = null;

    #[Url]
    public ?int $batchTo = null;

    /** @var array<int, array{id: int, prompt: string}> */
    public array $questions = [];

    /** @var array<int, array{hanzi: string, pinyin: string, arti: string}> */
    public array $answers = [];

    /** @var array<int, array{status: string, detail: ?string}|null> */
    public array $results = [];

    public bool $checked = false;

    public function mount(): void
    {
        $this->generateQuestions();
    }

    public function updatedModeSoal(): void
    {
        $this->generateQuestions();
    }

    public function updatedJumlah(): void
    {
        $this->generateQuestions();
    }

    public function updatedUrutan(): void
    {
        $this->generateQuestions();
    }

    #[Computed]
    public function totalPool(): int
    {
        return $this->scopedWords()->count();
    }

    /** @return Builder<MandarinWord> */
    protected function scopedWords(): Builder
    {
        $query = MandarinWord::query();

        if ($this->batchFrom !== null && $this->batchTo !== null) {
            $query->whereBetween('batch_number', [$this->batchFrom, $this->batchTo]);
        }

        return $query;
    }

    /** The two answer fields (in display order) the current mode asks for. */
    protected function fieldsForMode(): array
    {
        return match ($this->modeSoal) {
            'pinyin_from_hanzi' => ['pinyin', 'arti'],
            'both_from_meaning' => ['hanzi', 'pinyin'],
            default => ['hanzi', 'arti'],
        };
    }

    protected function promptFor(MandarinWord $word): string
    {
        return match ($this->modeSoal) {
            'pinyin_from_hanzi' => $word->hanzi,
            'both_from_meaning' => $word->meaning_id,
            default => $word->pinyin,
        };
    }

    public function promptLabel(): string
    {
        return match ($this->modeSoal) {
            'pinyin_from_hanzi' => 'Hanzi',
            'both_from_meaning' => 'Arti',
            default => 'Pinyin',
        };
    }

    protected function generateQuestions(): void
    {
        $query = $this->scopedWords();

        $query = $this->urutan === 'random'
            ? $query->inRandomOrder()
            : $query->orderBy('batch_number')->orderBy('order_in_batch');

        if ($this->jumlah !== 'all') {
            $query->limit((int) $this->jumlah);
        }

        $words = $query->get();

        $this->questions = $words->map(fn (MandarinWord $word) => [
            'id' => $word->id,
            'prompt' => $this->promptFor($word),
        ])->all();

        $this->resetAnswers();
    }

    public function resetAnswers(): void
    {
        $this->answers = array_fill(0, count($this->questions), ['hanzi' => '', 'pinyin' => '', 'arti' => '']);
        $this->results = array_fill(0, count($this->questions), null);
        $this->checked = false;
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'hanzi' => 'Hanzi',
            'pinyin' => 'Pinyin',
            'arti' => 'Arti',
        };
    }

    protected function fieldMatches(AnswerChecker $checker, string $field, string $userValue, MandarinWord $word): bool
    {
        return match ($field) {
            'hanzi' => $checker->hanziMatches($userValue, $word->hanzi),
            'pinyin' => $checker->pinyinMatches($userValue, $word->pinyin),
            'arti' => $checker->meaningMatches($userValue, $word->meaning_id),
        };
    }

    protected function correctValue(string $field, MandarinWord $word): string
    {
        return match ($field) {
            'hanzi' => $word->hanzi,
            'pinyin' => $word->pinyin,
            'arti' => $word->meaning_id,
        };
    }

    public function checkAnswers(): void
    {
        $checker = app(AnswerChecker::class);
        $fields = $this->fieldsForMode();
        $wordsById = MandarinWord::whereIn('id', array_column($this->questions, 'id'))->get()->keyBy('id');

        $results = [];

        foreach ($this->questions as $i => $question) {
            $word = $wordsById->get($question['id']);
            $given = $this->answers[$i] ?? ['hanzi' => '', 'pinyin' => '', 'arti' => ''];

            $isBlank = collect($fields)->every(fn ($field) => trim($given[$field] ?? '') === '');

            if ($isBlank) {
                $results[$i] = ['status' => 'empty', 'detail' => null];

                continue;
            }

            $wrongParts = [];

            foreach ($fields as $field) {
                $userValue = $given[$field] ?? '';

                if (! $this->fieldMatches($checker, $field, $userValue, $word)) {
                    $wrongParts[] = "{$this->fieldLabel($field)}: {$this->correctValue($field, $word)}";
                }
            }

            $results[$i] = empty($wrongParts)
                ? ['status' => 'correct', 'detail' => null]
                : ['status' => 'wrong', 'detail' => implode(' | ', $wrongParts)];
        }

        $this->results = $results;
        $this->checked = true;
    }

    #[Computed]
    public function summary(): array
    {
        $counts = ['total' => count($this->questions), 'benar' => 0, 'salah' => 0, 'kosong' => 0];

        foreach ($this->results as $result) {
            match ($result['status'] ?? null) {
                'correct' => $counts['benar']++,
                'wrong' => $counts['salah']++,
                'empty' => $counts['kosong']++,
                default => null,
            };
        }

        return $counts;
    }

    public function render()
    {
        $fields = $this->fieldsForMode();

        return view('livewire.latihan-isian', [
            'fields' => $fields,
            'fieldLabels' => collect($fields)->mapWithKeys(fn ($f) => [$f => $this->fieldLabel($f)])->all(),
        ]);
    }
}
