<?php

namespace App\Services;

use App\Models\IeltsVocab;
use App\Models\IrregularVerb;
use App\Models\MandarinWord;
use App\Models\WeekTopic;
use Illuminate\Support\Collection;

/**
 * Generates multiple-choice mock test questions server-side (spec section 8 & 12c):
 * shuffling and correct-answer selection must happen in PHP, not in the browser,
 * so the answer key never ships to the client before submission. Callers must
 * keep the 'answer_key' out of anything rendered to the page and only compare it
 * server-side against submitted answers.
 */
class ExamGenerator
{
    public function mandarin(int $n = 50): array
    {
        $all = MandarinWord::all();
        $n = min($n, $all->count());
        $selected = $all->random($n);

        return $this->buildQuestions(
            $selected,
            fn ($word) => $word->hanzi.' ('.$word->pinyin.')',
            fn ($word) => $word->meaning_id,
            $all->pluck('meaning_id'),
        );
    }

    public function english(int $n = 50): array
    {
        $verbs = IrregularVerb::all();
        $vocab = IeltsVocab::all();

        $verbPool = $verbs->pluck('past_form');
        $vocabPool = $vocab->pluck('meaning_id');

        $verbQuestions = $verbs->map(fn ($v) => [
            'prompt' => "Bentuk past tense dari '{$v->base_form}'?",
            'answer' => $v->past_form,
            'pool' => $verbPool,
        ]);

        $vocabQuestions = $vocab->map(fn ($w) => [
            'prompt' => "Arti dari '{$w->word_en}'?",
            'answer' => $w->meaning_id,
            'pool' => $vocabPool,
        ]);

        $combined = $verbQuestions->concat($vocabQuestions)->shuffle();
        $n = min($n, $combined->count());
        $selected = $combined->take($n);

        return $this->assembleFromPreparedQuestions($selected);
    }

    /** Scoped to whatever vocab is active on the given day (spec section 6, "Tes Hari Ini"). */
    public function today(int $day, Scheduler $scheduler): array
    {
        $prepared = collect();

        $mandarinStatus = $scheduler->mandarinStatus($day);
        if (in_array($mandarinStatus['type'], ['vocab', 'final'], true)) {
            $words = MandarinWord::where('batch_number', $mandarinStatus['batch_number'])->get();
            $pool = MandarinWord::pluck('meaning_id');
            $prepared = $prepared->concat($words->map(fn ($w) => [
                'prompt' => $w->hanzi.' ('.$w->pinyin.')',
                'answer' => $w->meaning_id,
                'pool' => $pool,
            ]));
        }

        $englishStatus = $scheduler->englishStatus($day);
        if ($englishStatus['type'] === 'active' && in_array($englishStatus['cycle'], [1, 2, 3, 4, 5], true)) {
            $topics = WeekTopic::where('week_number', $englishStatus['week_number'])->orderBy('topic_order')->pluck('topic_name')->values();
            $topicIndex = match (true) {
                in_array($englishStatus['cycle'], [1, 2], true) => 0,
                in_array($englishStatus['cycle'], [3, 4], true) => 1,
                default => 2,
            };
            $topic = $topics->get($topicIndex);

            if ($topic) {
                $vocab = IeltsVocab::where('topic', $topic)->get();
                $pool = IeltsVocab::pluck('meaning_id');
                $prepared = $prepared->concat($vocab->map(fn ($w) => [
                    'prompt' => "Arti dari '{$w->word_en}'?",
                    'answer' => $w->meaning_id,
                    'pool' => $pool,
                ]));
            }
        }

        return $this->assembleFromPreparedQuestions($prepared->shuffle());
    }

    private function assembleFromPreparedQuestions(Collection $prepared): array
    {
        $questions = [];
        $answerKey = [];

        foreach ($prepared as $q) {
            $distractorPool = $q['pool']->reject(fn ($a) => $a === $q['answer'])->unique()->values();
            $distractors = $distractorPool->count() > 0
                ? $distractorPool->random(min(3, $distractorPool->count()))->all()
                : [];
            $options = array_merge([$q['answer']], $distractors);
            shuffle($options);

            $questions[] = ['prompt' => $q['prompt'], 'options' => $options];
            $answerKey[] = array_search($q['answer'], $options, true);
        }

        return ['questions' => $questions, 'answer_key' => $answerKey];
    }

    private function buildQuestions(Collection $selected, callable $promptFn, callable $answerFn, Collection $answerPool): array
    {
        $questions = [];
        $answerKey = [];

        foreach ($selected as $item) {
            $answer = $answerFn($item);
            $distractorPool = $answerPool->reject(fn ($a) => $a === $answer)->unique()->values();
            $distractors = $distractorPool->random(min(3, $distractorPool->count()))->all();
            $options = array_merge([$answer], $distractors);
            shuffle($options);

            $questions[] = ['prompt' => $promptFn($item), 'options' => $options];
            $answerKey[] = array_search($answer, $options, true);
        }

        return ['questions' => $questions, 'answer_key' => $answerKey];
    }
}
