<?php

namespace App\Services;

use App\Models\GenericTip;
use App\Models\IeltsVocab;
use App\Models\InfoDetail;
use App\Models\IrregularVerb;
use App\Models\MandarinConversation;
use App\Models\MandarinWeekGrammar;
use App\Models\MandarinWord;
use App\Models\PastTenseStep;
use App\Models\PlatformLink;
use App\Models\WeekTopic;

/**
 * Day-relative scheduling algorithm (spec section 4). Every user has their own
 * start_date; all schedule math is driven by day_number = days since start_date + 1,
 * never by calendar weekday, so any user can start on any date and get the same
 * progression from Day 1.
 */
class Scheduler
{
    public const MANDARIN_TOTAL_DAYS = 26;

    public const ENGLISH_TOTAL_DAYS = 62;

    private const WEEK_LETTERS = ['A', 'B', 'C', 'D'];

    /*
    |--------------------------------------------------------------------------
    | 4a. Mandarin (HSK1) track
    |--------------------------------------------------------------------------
    */

    public function mandarinStatus(int $day): array
    {
        if ($day < 1) {
            return ['type' => 'not_started'];
        }

        if ($day === 1) {
            return ['type' => 'foundation', 'foundation_day' => 1];
        }

        if ($day === 2) {
            return ['type' => 'foundation', 'foundation_day' => 2];
        }

        if ($day > self::MANDARIN_TOTAL_DAYS) {
            return ['type' => 'done'];
        }

        $d = $day - 3;
        $cycle = $d % 7;

        if ($cycle === 6) {
            $prevBatchIndex = $this->mandarinBatchIndexForOffset($d - 1);
            $weekLetter = self::WEEK_LETTERS[min(3, intdiv($prevBatchIndex, 6))];

            return ['type' => 'review', 'week_letter' => $weekLetter];
        }

        $batchIndex = $this->mandarinBatchIndexForOffset($d);
        $weekLetter = self::WEEK_LETTERS[min(3, intdiv($batchIndex, 6))];
        $isFinal = $batchIndex === 20;

        return [
            'type' => $isFinal ? 'final' : 'vocab',
            'batch_index' => $batchIndex,
            'batch_number' => $batchIndex + 1,
            'week_letter' => $weekLetter,
        ];
    }

    /** batch_index = d - floor(d/7), 0-indexed into the 21 vocab batches. */
    private function mandarinBatchIndexForOffset(int $d): int
    {
        return $d - intdiv($d, 7);
    }

    /*
    |--------------------------------------------------------------------------
    | 4b. English (IELTS) track
    |--------------------------------------------------------------------------
    */

    public function englishStatus(int $day): array
    {
        if ($day < 1) {
            return ['type' => 'not_started'];
        }

        if ($day > self::ENGLISH_TOTAL_DAYS) {
            return ['type' => 'done'];
        }

        $phase = $day <= 21 ? 'V1' : ($day <= 42 ? 'V2' : 'V3');
        $weekNumber = min((int) ceil($day / 7), 9);
        $cycle = (($day - 1) % 7) + 1;

        return [
            'type' => 'active',
            'phase' => $phase,
            'week_number' => $weekNumber,
            'cycle' => $cycle,
        ];
    }

    /**
     * Simple-past grammar slot (0..14) for V1 weekdays. Spec text says slots run
     * "day 3 to day 21", but that only yields 13 weekday occurrences — one short
     * of the 15 entries in past_tense_steps. Counting from day 1 (matching cycle 1
     * Monday / cycle 2 Tuesday, which are ordinary lesson days for English even
     * though they're Mandarin foundation days) yields exactly 15, so slots are
     * assigned from day 1 to keep the array in bounds and fully used.
     */
    public function pastTenseSlot(int $day): ?int
    {
        if ($day < 1 || $day > 21) {
            return null;
        }

        $cycle = (($day - 1) % 7) + 1;
        if ($cycle > 5) {
            return null;
        }

        $count = 0;
        for ($d = 1; $d <= $day; $d++) {
            if ((($d - 1) % 7) + 1 <= 5) {
                $count++;
            }
        }

        return $count - 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Daily checklist item builders (spec section 6)
    |--------------------------------------------------------------------------
    */

    public function mandarinItems(int $day): array
    {
        $status = $this->mandarinStatus($day);

        return match ($status['type']) {
            'foundation' => $this->mandarinFoundationItems($status['foundation_day']),
            'review' => $this->mandarinReviewItems($status['week_letter']),
            'vocab', 'final' => $this->mandarinVocabItems($status),
            default => [],
        };
    }

    /**
     * The classic 妈/麻/马/骂 (mā/má/mǎ/mà) example set — not sourced from any
     * table (info_details only holds descriptive text about pinyin, no
     * example syllables of its own), but the standard illustration of the 4
     * tones on the same syllable, so it's a reasonable, self-contained
     * "hear the tones" aid for the pinyin-foundation days.
     */
    private const TONE_EXAMPLES = [
        ['hanzi' => '妈', 'pinyin' => 'mā'],
        ['hanzi' => '麻', 'pinyin' => 'má'],
        ['hanzi' => '马', 'pinyin' => 'mǎ'],
        ['hanzi' => '骂', 'pinyin' => 'mà'],
    ];

    private function mandarinFoundationItems(int $foundationDay): array
    {
        $details = InfoDetail::where('day_number', $foundationDay)->orderBy('order_in_day')->get();

        return [[
            'id' => 'mandarin-foundation',
            'lang' => 'mandarin',
            'type' => 'info',
            'title' => "Fondasi Pinyin — Hari {$foundationDay}",
            'subtitle' => $details->pluck('label_key')->implode(' · '),
            'payload' => [
                'details' => $details->map(fn ($d) => ['label' => $d->label_key, 'text' => $d->detail_text])->all(),
                'tone_examples' => self::TONE_EXAMPLES,
            ],
        ]];
    }

    /**
     * batch_number range (1-indexed, inclusive) that mandarinStatus() groups
     * under $weekLetter — mirrors the intdiv($batchIndex, 6) clamp-to-3 logic
     * there, so "this week's material" for the review/quiz internal-practice
     * link always matches what the user actually studied that week. Week D
     * is only 3 batches (19-21), the other three are 6 each.
     */
    private function mandarinBatchRangeForWeek(string $weekLetter): array
    {
        $weekIndex = array_search($weekLetter, self::WEEK_LETTERS, true);

        return [
            'from' => $weekIndex * 6 + 1,
            'to' => $weekIndex === 3 ? 21 : $weekIndex * 6 + 6,
        ];
    }

    private function mandarinReviewItems(string $weekLetter): array
    {
        $grammar = MandarinWeekGrammar::where('week_letter', $weekLetter)->first();
        $conversations = MandarinConversation::where('week_letter', $weekLetter)->orderBy('order_in_week')->get();
        $batchRange = $this->mandarinBatchRangeForWeek($weekLetter);

        return [
            [
                'id' => 'mandarin-review',
                'lang' => 'mandarin',
                'type' => 'review',
                'title' => "Review Mingguan Mandarin (Minggu {$weekLetter})",
                'subtitle' => null,
                'payload' => ['tip' => $this->tip('review'), 'isian_batch_from' => $batchRange['from'], 'isian_batch_to' => $batchRange['to']],
            ],
            [
                'id' => 'mandarin-dictation',
                'lang' => 'mandarin',
                'type' => 'dictation',
                'title' => 'Dikte Mendengarkan',
                'subtitle' => null,
                'payload' => ['tip' => $this->tip('dictation'), 'listening_text' => $grammar?->listening_text],
            ],
            [
                'id' => 'mandarin-conversation',
                'lang' => 'mandarin',
                'type' => 'conversation',
                'title' => 'Latihan Percakapan',
                'subtitle' => "Minggu {$weekLetter}",
                'payload' => ['pairs' => $conversations->toArray(), 'grammar' => $grammar?->grammar_text],
            ],
            [
                'id' => 'mandarin-quiz',
                'lang' => 'mandarin',
                'type' => 'quiz',
                'title' => 'Kuis Mandiri',
                'subtitle' => null,
                'payload' => ['tip' => $this->tip('quiz'), 'isian_batch_from' => $batchRange['from'], 'isian_batch_to' => $batchRange['to']],
            ],
        ];
    }

    private function mandarinVocabItems(array $status): array
    {
        $batchNumber = $status['batch_number'];
        $weekLetter = $status['week_letter'];
        $isFinal = $status['type'] === 'final';

        $words = MandarinWord::where('batch_number', $batchNumber)->orderBy('order_in_batch')->get();
        $grammar = MandarinWeekGrammar::where('week_letter', $weekLetter)->first();
        $conversations = MandarinConversation::where('week_letter', $weekLetter)->orderBy('order_in_week')->get();
        $batchRange = $this->mandarinBatchRangeForWeek($weekLetter);

        $items = [
            [
                'id' => 'mandarin-vocab',
                'lang' => 'mandarin',
                'type' => 'vocab',
                'title' => $isFinal ? "Vocab Terakhir (Batch {$batchNumber})" : "Vocab Baru (Batch {$batchNumber})",
                'subtitle' => $words->count().' kata baru',
                'payload' => ['words' => $words->toArray(), 'batch_number' => $batchNumber],
            ],
            [
                'id' => 'mandarin-grammar',
                'lang' => 'mandarin',
                'type' => 'grammar',
                'title' => "Grammar Minggu {$weekLetter}",
                'subtitle' => null,
                'payload' => ['grammar' => $grammar?->grammar_text, 'listening' => $grammar?->listening_text],
            ],
            [
                'id' => 'mandarin-conversation',
                'lang' => 'mandarin',
                'type' => 'conversation',
                'title' => 'Latihan Percakapan',
                'subtitle' => "Minggu {$weekLetter}",
                'payload' => ['pairs' => $conversations->toArray()],
            ],
            [
                'id' => 'mandarin-quiz',
                'lang' => 'mandarin',
                'type' => 'quiz',
                'title' => 'Kuis Mandiri',
                'subtitle' => null,
                'payload' => ['tip' => $this->tip('quiz'), 'isian_batch_from' => $batchRange['from'], 'isian_batch_to' => $batchRange['to']],
            ],
        ];

        if ($isFinal) {
            $items[] = [
                'id' => 'mandarin-review',
                'lang' => 'mandarin',
                'type' => 'review',
                'title' => 'Review Total HSK1',
                'subtitle' => 'Semua 150 kata',
                // Deliberately no isian_batch_from/to: this is a review of
                // ALL 150 words, not just this week's — the "Mulai Latihan
                // Isian" button should link to the unfiltered full pool.
                'payload' => ['tip' => $this->tip('review')],
            ];
        }

        return $items;
    }

    public function englishItems(int $day): array
    {
        $status = $this->englishStatus($day);
        if ($status['type'] !== 'active') {
            return [];
        }

        $phase = $status['phase'];
        $cycle = $status['cycle'];
        $weekNumber = $status['week_number'];

        $topics = WeekTopic::where('week_number', $weekNumber)->orderBy('topic_order')->pluck('topic_name')->values();

        $items = [];

        $grammarItem = $this->englishGrammarItem($day, $phase);
        if ($grammarItem) {
            $items[] = $grammarItem;
        }

        switch ($cycle) {
            case 1:
                $items[] = $this->vocabItem($topics->get(0));
                $items[] = $this->listeningItem($topics->get(0));
                $items[] = $this->speakingItem('Part 1', $topics->get(0));
                break;
            case 2:
                $items[] = $this->vocabItem($topics->get(0));
                $items[] = $this->readingItem($topics->get(0));
                $items[] = $this->speakingItem('Part 3', $topics->get(0));
                break;
            case 3:
                $items[] = $this->vocabItem($topics->get(1));
                $items[] = $this->listeningItem($topics->get(1));
                $items[] = $this->speakingItem('Part 1', $topics->get(1));
                break;
            case 4:
                $items[] = $this->vocabItem($topics->get(1));
                $items[] = $this->readingItem($topics->get(1));
                $items[] = $this->speakingItem('Part 3', $topics->get(1));
                break;
            case 5:
                $items[] = $this->vocabItem($topics->get(2));
                $items[] = $this->writingItem($topics->get(2));
                $items[] = $this->speakingItem('Part 2', $topics->get(2));
                break;
            case 6:
                if ($phase === 'V3') {
                    $items[] = $this->mockItem(true);
                } else {
                    $items[] = $this->speakingItem('Simulasi', $topics->get(0));
                    $items[] = $this->mockItem(false);
                }
                break;
            case 7:
                $items[] = $this->reviewItem();
                break;
        }

        return $items;
    }

    private function englishGrammarItem(int $day, string $phase): ?array
    {
        $cycle = (($day - 1) % 7) + 1;
        if ($cycle > 5) {
            return null;
        }

        if ($phase === 'V1') {
            $slot = $this->pastTenseSlot($day);
            if ($slot === null) {
                return null;
            }

            $step = PastTenseStep::where('slot_number', $slot)->first();
            if (! $step) {
                return null;
            }

            $verbs = $step->batch_number_ref
                ? IrregularVerb::where('batch_number', $step->batch_number_ref)->orderBy('order_in_batch')->get()
                : collect();

            $link = $step->platform_link_key ? PlatformLink::where('link_key', $step->platform_link_key)->first() : null;

            return [
                'id' => 'english-grammar',
                'lang' => 'english',
                'type' => 'grammar',
                'title' => $step->title,
                'subtitle' => 'Simple Past Tense',
                'payload' => [
                    'detail' => $step->detail,
                    'verbs' => $verbs->toArray(),
                    'link' => $link ? ['name' => $link->name, 'url' => $link->url] : null,
                ],
            ];
        }

        if ($phase === 'V2') {
            $isOdd = $day % 2 === 1;
            $content = $isOdd
                ? [
                    'title' => 'Present Perfect Tense',
                    'detail' => "Present perfect (have/has + V3) dipakai untuk pengalaman tanpa waktu spesifik, atau aksi yang efeknya masih terasa sekarang. Contoh: 'I have visited Beijing three times.' Beda dengan simple past yang butuh waktu spesifik: 'I visited Beijing in 2019.'",
                ]
                : [
                    'title' => 'Passive Voice',
                    'detail' => "Passive voice (is/am/are/was/were + V3) dipakai saat fokus pada objek yang menerima aksi, bukan pelakunya. Aktif: 'They built this bridge in 1990.' Passive: 'This bridge was built in 1990.'",
                ];

            return [
                'id' => 'english-grammar',
                'lang' => 'english',
                'type' => 'grammar',
                'title' => $content['title'],
                'subtitle' => 'Fase V2',
                'payload' => ['detail' => $content['detail'], 'verbs' => [], 'link' => null],
            ];
        }

        // V3
        return [
            'id' => 'english-grammar',
            'lang' => 'english',
            'type' => 'grammar',
            'title' => 'Grammar Lanjutan & Review Campuran',
            'subtitle' => 'Fase V3',
            'payload' => [
                'detail' => 'Gabungkan present perfect, passive voice, dan simple past dalam satu paragraf opini/argumentasi — pola yang sering muncul di IELTS Writing Task 2 dan Speaking Part 3.',
                'verbs' => [],
                'link' => null,
            ],
        ];
    }

    private function vocabItem(?string $topic): array
    {
        $words = $topic ? IeltsVocab::where('topic', $topic)->get() : collect();

        return [
            'id' => 'english-vocab',
            'lang' => 'english',
            'type' => 'vocab',
            'title' => 'Vocab: '.($topic ?? '-'),
            'subtitle' => $words->count().' kata baru',
            'payload' => ['topic' => $topic, 'words' => $words->toArray()],
        ];
    }

    /**
     * No real listening-comprehension feature exists yet, so "Dengar &
     * Ulangi" is a simple stand-in: TTS through this same cycle's vocab
     * words (english_vocab has no audio of its own to attach here, and this
     * item's $topic always matches the vocab item scheduled the same cycle
     * — see englishItems()) one at a time via speak(), reusing the sequencer
     * speakSequence() already built for the irregular-verb cards feature.
     */
    private function listeningItem(?string $topic): array
    {
        $words = $topic ? IeltsVocab::where('topic', $topic)->pluck('word_en')->all() : [];

        return [
            'id' => 'english-listening',
            'lang' => 'english',
            'type' => 'listening',
            'title' => 'Listening: '.($topic ?? '-'),
            'subtitle' => null,
            'payload' => ['topic' => $topic, 'tip' => $this->tip('listening'), 'words' => $words],
        ];
    }

    private function readingItem(?string $topic): array
    {
        return [
            'id' => 'english-reading',
            'lang' => 'english',
            'type' => 'reading',
            'title' => 'Reading: '.($topic ?? '-'),
            'subtitle' => null,
            'payload' => ['topic' => $topic, 'tip' => $this->tip('reading')],
        ];
    }

    private function speakingItem(string $part, ?string $topic): array
    {
        $topic ??= 'topik campuran';

        return [
            'id' => 'english-speaking',
            'lang' => 'english',
            'type' => 'speaking',
            'title' => "Speaking {$part}: {$topic}",
            'subtitle' => null,
            'payload' => ['part' => $part, 'topic' => $topic, 'prompts' => SpeakingPrompts::generate($part, $topic)],
        ];
    }

    private function writingItem(?string $topic): array
    {
        $topic ??= 'isu ini';

        return [
            'id' => 'english-writing',
            'lang' => 'english',
            'type' => 'writing',
            'title' => 'Writing: '.$topic,
            'subtitle' => null,
            'payload' => ['topic' => $topic, 'prompt' => SpeakingPrompts::writingPrompt($topic)],
        ];
    }

    private function mockItem(bool $full): array
    {
        return [
            'id' => 'english-mock',
            'lang' => 'english',
            'type' => 'mock',
            'title' => $full ? 'Full Mock Test' : 'Speaking Simulasi + Mini Mock',
            'subtitle' => null,
            'payload' => ['full' => $full, 'tip' => $this->tip('mock')],
        ];
    }

    private function reviewItem(): array
    {
        return [
            'id' => 'english-review',
            'lang' => 'english',
            'type' => 'review',
            'title' => 'Review Mingguan English',
            'subtitle' => null,
            'payload' => ['tip' => $this->tip('review')],
        ];
    }

    private function tip(string $itemType): ?string
    {
        return GenericTip::where('item_type', $itemType)->value('tip_text');
    }

    /*
    |--------------------------------------------------------------------------
    | Combined plan for a user (respects onboarding track)
    |--------------------------------------------------------------------------
    */

    public function planForDay(int $day, string $track = 'both'): array
    {
        $items = [];

        if (in_array($track, ['mandarin', 'both'], true)) {
            $items = array_merge($items, $this->mandarinItems($day));
        }

        if (in_array($track, ['english', 'both'], true)) {
            $items = array_merge($items, $this->englishItems($day));
        }

        return array_map(fn ($item) => $item + ['links' => $this->platformLinksFor($item)], $items);
    }

    /*
    |--------------------------------------------------------------------------
    | Platform link mapping (spec section 13): item type -> platform_link_key
    |--------------------------------------------------------------------------
    */

    public function platformLinksFor(array $item): array
    {
        $lang = $item['lang'];
        $type = $item['type'];
        $payload = $item['payload'] ?? [];

        // English V1 grammar slots each carry their own specific link (past_tense_steps.platform_link_key).
        if ($lang === 'english' && $type === 'grammar' && ! empty($payload['link'])) {
            return [$payload['link']];
        }

        $keys = match (true) {
            $lang === 'mandarin' && $type === 'vocab' => ['hskuni', 'archchinese', 'pleco'],
            $lang === 'mandarin' && in_array($type, ['grammar', 'listening'], true) => ['hskuni'],
            $lang === 'mandarin' && $type === 'info' => ['hellochinese', 'pleco'],
            $lang === 'mandarin' && in_array($type, ['review', 'quiz'], true) => ['pleco'],
            $lang === 'mandarin' => ['hskuni'],

            $lang === 'english' && $type === 'vocab' => ['ieltsliz', 'ieltspodcast'],
            $lang === 'english' && $type === 'grammar' => ['britishcouncil'],
            $lang === 'english' && $type === 'speaking' => ['keithspeaking'],
            $lang === 'english' && $type === 'writing' => ['ieltsliz'],
            $lang === 'english' && in_array($type, ['reading', 'listening'], true) => ['cambridge'],
            $lang === 'english' && in_array($type, ['review', 'mock']) => ['ieltsliz'],
            default => ['ieltsliz'],
        };

        return $this->linksByKeys($keys);
    }

    private function linksByKeys(array $keys): array
    {
        $links = PlatformLink::whereIn('link_key', $keys)->get()->keyBy('link_key');

        return collect($keys)
            ->map(fn ($key) => $links->get($key))
            ->filter()
            ->map(fn ($link) => ['name' => $link->name, 'url' => $link->url])
            ->values()
            ->all();
    }
}
