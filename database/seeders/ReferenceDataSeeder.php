<?php

namespace Database\Seeders;

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
use App\Services\AnswerChecker;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    protected string $dataPath;

    public function __construct()
    {
        $this->dataPath = database_path('seeders/data');
    }

    protected function loadJson(string $file): mixed
    {
        return json_decode(file_get_contents($this->dataPath.'/'.$file), true, 512, JSON_THROW_ON_ERROR);
    }

    public function run(): void
    {
        $this->seedMandarinWords();
        $this->seedIrregularVerbs();
        $this->seedIeltsVocab();
        $this->seedWeekTopics();
        $this->seedPastTenseSteps();
        $this->seedMandarinWeekGrammar();
        $this->seedInfoDetails();
        $this->seedGenericTips();
        $this->seedPlatformLinks();
        $this->seedMandarinConversations();
    }

    protected function seedMandarinWords(): void
    {
        $words = $this->loadJson('mandarin_words.json');
        $english = $this->loadJson('mandarin_words_en.json');
        $batches = $this->loadJson('mandarin_batches.json');

        $checker = new AnswerChecker;

        $rows = [];
        foreach ($batches as $batchIndex => [$start, $end]) {
            $batchNumber = $batchIndex + 1;
            $orderInBatch = 1;
            for ($i = $start; $i <= $end; $i++) {
                $wordIndex = $i - 1; // 1-indexed range -> 0-indexed array
                [$hanzi, $pinyin, $meaningId] = $words[$wordIndex];
                $rows[] = [
                    'hanzi' => $hanzi,
                    'pinyin' => $pinyin,
                    'pinyin_normalized' => $checker->normalizePinyin($pinyin),
                    'meaning_id' => $meaningId,
                    'meaning_en' => $english[$wordIndex],
                    'batch_number' => $batchNumber,
                    'order_in_batch' => $orderInBatch++,
                    'hsk_level_old' => 1,
                    'hsk_level_new' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        MandarinWord::insert($rows);
    }

    protected function seedIrregularVerbs(): void
    {
        $verbs = $this->loadJson('irregular_verbs.json');
        $rows = [];
        foreach ($verbs as $i => [$base, $past, $pastParticiple, $meaningId, $example]) {
            $batchNumber = intdiv($i, 5) + 1;
            $orderInBatch = ($i % 5) + 1;
            $rows[] = [
                'base_form' => $base,
                'past_form' => $past,
                'past_participle' => $pastParticiple,
                'meaning_id' => $meaningId,
                'example_sentence' => $example,
                'batch_number' => $batchNumber,
                'order_in_batch' => $orderInBatch,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        IrregularVerb::insert($rows);
    }

    protected function seedIeltsVocab(): void
    {
        $vocab = $this->loadJson('ielts_vocab.json');
        $rows = [];
        foreach ($vocab as [$topic, $wordEn, $meaningId, $example]) {
            $rows[] = [
                'topic' => $topic,
                'word_en' => $wordEn,
                'meaning_id' => $meaningId,
                'example_sentence' => $example,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        IeltsVocab::insert($rows);
    }

    protected function seedWeekTopics(): void
    {
        $weeks = $this->loadJson('week_topics.json');
        $rows = [];
        foreach ($weeks as $weekIndex => $topics) {
            foreach ($topics as $topicOrder => $topicName) {
                $rows[] = [
                    'week_number' => $weekIndex + 1,
                    'topic_order' => $topicOrder,
                    'topic_name' => $topicName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        WeekTopic::insert($rows);
    }

    protected function seedPastTenseSteps(): void
    {
        $steps = $this->loadJson('past_tense_steps.json');
        $rows = [];
        foreach ($steps as $i => $step) {
            $rows[] = [
                'slot_number' => $i,
                'title' => $step['title'],
                'detail' => $step['detail'],
                'batch_number_ref' => $step['batch'] ?? null,
                'platform_link_key' => $step['link'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        PastTenseStep::insert($rows);
    }

    protected function seedMandarinWeekGrammar(): void
    {
        $grammar = $this->loadJson('mandarin_week_grammar.json');
        $rows = [];
        foreach ($grammar as $letter => $content) {
            $rows[] = [
                'week_letter' => $letter,
                'grammar_text' => $content['grammar'],
                'listening_text' => $content['listening'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        MandarinWeekGrammar::insert($rows);
    }

    protected function seedInfoDetails(): void
    {
        $details = $this->loadJson('info_details.json');
        $entries = array_values($details);
        $labels = array_keys($details);

        // First 4 entries = Fondasi Pinyin Hari 1, last 4 = Hari 2 (see spec section 9j ordering)
        $rows = [];
        foreach ($labels as $i => $label) {
            $dayNumber = $i < 4 ? 1 : 2;
            $orderInDay = ($i % 4) + 1;
            $rows[] = [
                'label_key' => $label,
                'detail_text' => $entries[$i],
                'day_number' => $dayNumber,
                'order_in_day' => $orderInDay,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        InfoDetail::insert($rows);
    }

    protected function seedGenericTips(): void
    {
        $tips = $this->loadJson('generic_tips.json');
        $rows = [];
        foreach ($tips as $itemType => $text) {
            $rows[] = [
                'item_type' => $itemType,
                'tip_text' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        GenericTip::insert($rows);
    }

    protected function seedPlatformLinks(): void
    {
        $links = $this->loadJson('platform_links.json');
        $rows = [];
        foreach ($links as $key => $link) {
            $rows[] = [
                'link_key' => $key,
                'name' => $link['name'],
                'url' => $link['url'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        PlatformLink::insert($rows);
    }

    protected function seedMandarinConversations(): void
    {
        $conversations = $this->loadJson('mandarin_conversations.json');
        $rows = [];
        foreach ($conversations as $letter => $pairs) {
            foreach ($pairs as $order => $pair) {
                $rows[] = [
                    'week_letter' => $letter,
                    'order_in_week' => $order,
                    'q_hanzi' => $pair['q_hanzi'],
                    'q_pinyin' => $pair['q_pinyin'],
                    'q_id' => $pair['q_id'],
                    'a_hanzi' => $pair['a_hanzi'],
                    'a_pinyin' => $pair['a_pinyin'],
                    'a_id' => $pair['a_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        MandarinConversation::insert($rows);
    }
}
