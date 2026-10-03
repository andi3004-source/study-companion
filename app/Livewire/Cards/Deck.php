<?php

namespace App\Livewire\Cards;

use App\Models\IeltsVocab;
use App\Models\UserWordProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.study')]
class Deck extends Component
{
    /**
     * mandarin_hsk_words and irregular_verbs_reference start their own id
     * sequences at 1, colliding with mandarin_words (1-150) and the old,
     * no-longer-shown irregular_verbs (1-35) respectively. user_word_progress
     * tracks "known" by (word_type, word_id) with no room for a third enum
     * value, so reference-table ids are offset into disjoint ranges instead
     * — this is what keeps HSK1 vs HSK2+ (and old vs new verb) progress from
     * ever being confused for the same card.
     */
    private const HSK_REFERENCE_ID_OFFSET = 1_000_000;

    private const VERB_REFERENCE_ID_OFFSET = 2_000_000;

    public string $deck;

    public string $mandarinLevel = '1';

    /**
     * Set via ?batch=N from the dashboard checklist's "Latihan Kartu Kata
     * Ini" button (a Mandarin vocab item knows its own batch_number) to show
     * only that day's batch instead of the whole level. Cleared by any
     * manual deck/level switch so it doesn't silently keep filtering after
     * the user navigates around inside Kartu themselves.
     */
    #[Url]
    public ?int $batch = null;

    /** Set via ?topic=... from the dashboard's English vocab item, same idea as $batch above but for the 'vocab' (IELTS) deck. */
    #[Url]
    public ?string $topic = null;

    public int $index = 0;

    public bool $flipped = false;

    public bool $hideKnown = false;

    public function mount(?string $deck = null): void
    {
        $this->deck = in_array($deck, ['mandarin', 'verb', 'vocab'], true) ? $deck : 'mandarin';
    }

    public function switchDeck(string $deck): void
    {
        $this->deck = $deck;
        $this->mandarinLevel = '1';
        $this->batch = null;
        $this->topic = null;
        $this->index = 0;
        $this->flipped = false;
        unset($this->cards, $this->knownIds);
    }

    public function setMandarinLevel(string $level): void
    {
        $this->mandarinLevel = $level;
        $this->batch = null;
        $this->index = 0;
        $this->flipped = false;
        unset($this->cards, $this->knownIds);
    }

    #[Computed]
    public function knownIds()
    {
        return UserWordProgress::where('user_id', Auth::id())
            ->where('word_type', $this->deck)
            ->where('is_known', true)
            ->pluck('word_id');
    }

    #[Computed]
    public function cards()
    {
        $items = match ($this->deck) {
            'mandarin' => $this->mandarinCards(),
            'verb' => $this->verbCards(),
            'vocab' => $this->topic !== null
                ? IeltsVocab::where('topic', $this->topic)->get()
                : IeltsVocab::orderBy('topic')->get(),
            default => collect(),
        };

        if ($this->hideKnown) {
            $items = $items->reject(fn ($item) => $this->knownIds->contains($item->id))->values();
        }

        return $items;
    }

    private function mandarinCards()
    {
        // A specific day's batch is curriculum-only (mandarin_hsk_words has
        // no batch_number — it's not part of the daily schedule), so this
        // skips the level filter and the reference-table union entirely.
        if ($this->batch !== null) {
            return DB::table('mandarin_words')
                ->select(['id', 'hanzi', 'pinyin', 'meaning_id', 'meaning_en', 'hsk_level_old'])
                ->where('batch_number', $this->batch)
                ->orderBy('order_in_batch')
                ->get();
        }

        $curriculum = DB::table('mandarin_words')->select([
            'id', 'hanzi', 'pinyin', 'meaning_id', 'meaning_en', 'hsk_level_old',
        ]);

        $reference = DB::table('mandarin_hsk_words')->select([
            DB::raw('id + '.self::HSK_REFERENCE_ID_OFFSET.' as id'),
            'hanzi', 'pinyin',
            DB::raw('COALESCE(meaning_id, meaning_en) as meaning_id'),
            'meaning_en', 'hsk_level_old',
        ]);

        if ($this->mandarinLevel !== 'all') {
            $level = (int) $this->mandarinLevel;
            $curriculum->where('hsk_level_old', $level);
            $reference->where('hsk_level_old', $level);
        }

        return DB::query()
            ->fromSub($curriculum->unionAll($reference), 'mandarin_cards')
            ->orderBy('hsk_level_old')
            ->orderBy('hanzi')
            ->get();
    }

    private function verbCards()
    {
        return DB::table('irregular_verbs_reference')
            ->select([
                DB::raw('id + '.self::VERB_REFERENCE_ID_OFFSET.' as id'),
                'base_form', 'past_form', 'past_participle', 'meaning_id',
            ])
            ->orderBy('frequency_rank')
            ->get();
    }

    public function flip(): void
    {
        $this->flipped = ! $this->flipped;
    }

    public function next(): void
    {
        $this->flipped = false;
        $this->index = min($this->index + 1, max(0, $this->cards->count() - 1));
    }

    public function prev(): void
    {
        $this->flipped = false;
        $this->index = max($this->index - 1, 0);
    }

    public function markKnown(bool $known): void
    {
        $card = $this->cards->get($this->index);
        if (! $card) {
            return;
        }

        UserWordProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'word_type' => $this->deck, 'word_id' => $card->id],
            ['is_known' => $known]
        );

        unset($this->knownIds);
        $this->next();
    }

    public function toggleHideKnown(): void
    {
        $this->hideKnown = ! $this->hideKnown;
        $this->index = 0;
        $this->flipped = false;
        unset($this->cards);
    }

    public function render()
    {
        return view('livewire.cards.deck');
    }
}
