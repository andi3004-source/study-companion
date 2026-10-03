<?php

namespace Tests\Feature;

use App\Livewire\LatihanIsian;
use App\Models\MandarinWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LatihanIsianTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(): User
    {
        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    protected function makeWord(array $overrides = []): MandarinWord
    {
        static $order = 0;
        $order++;

        return MandarinWord::create(array_merge([
            'hanzi' => '好',
            'pinyin' => 'hǎo',
            'meaning_id' => 'baik',
            'meaning_en' => 'good',
            'batch_number' => 1,
            'order_in_batch' => $order,
        ], $overrides));
    }

    public function test_page_loads(): void
    {
        $this->makeWord();
        $user = $this->onboardedUser();

        $this->actingAs($user)->get('/latihan-isian')->assertOk();
    }

    public function test_batch_range_filter_scopes_the_pool_to_that_weeks_material(): void
    {
        $this->makeWord(['hanzi' => '一', 'batch_number' => 1]);
        $this->makeWord(['hanzi' => '二', 'batch_number' => 6]);
        $this->makeWord(['hanzi' => '三', 'batch_number' => 7]); // outside the range below
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('batchFrom', 1)
            ->set('batchTo', 6)
            ->set('jumlah', 'all')
            ->assertSet('totalPool', 2)
            ->assertCount('questions', 2);
    }

    public function test_no_batch_range_uses_the_full_pool_as_before(): void
    {
        $this->makeWord(['hanzi' => '一', 'batch_number' => 1]);
        $this->makeWord(['hanzi' => '二', 'batch_number' => 20]);
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('jumlah', 'all')
            ->assertSet('totalPool', 2);
    }

    public function test_generating_pinyin_mode_produces_requested_row_count_with_blank_answers(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeWord(['hanzi' => "字{$i}", 'pinyin' => "zi{$i}"]);
        }
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('modeSoal', 'pinyin_from_hanzi')
            ->set('jumlah', '20')
            ->set('urutan', 'random')
            ->assertSet('answers.0.pinyin', '')
            ->assertSet('answers.0.arti', '')
            ->assertCount('questions', 20);
    }

    public function test_pinyin_answer_ignores_tone_marks(): void
    {
        $this->makeWord(['hanzi' => '好', 'pinyin' => 'hǎo', 'meaning_id' => 'baik']);
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('modeSoal', 'pinyin_from_hanzi')
            ->set('answers.0.pinyin', 'hao')
            ->set('answers.0.arti', 'baik')
            ->call('checkAnswers')
            ->assertSet('results.0.status', 'correct');
    }

    public function test_meaning_answer_accepts_a_synonym_variant(): void
    {
        $this->makeWord(['hanzi' => '爱', 'pinyin' => 'ài', 'meaning_id' => 'cinta / suka']);
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('modeSoal', 'pinyin_from_hanzi')
            ->set('answers.0.pinyin', 'ai')
            ->set('answers.0.arti', 'suka')
            ->call('checkAnswers')
            ->assertSet('results.0.status', 'correct');
    }

    public function test_typo_in_hanzi_is_marked_wrong_and_reveals_correct_hanzi(): void
    {
        $this->makeWord(['hanzi' => '你', 'pinyin' => 'nǐ', 'meaning_id' => 'kamu']);
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('modeSoal', 'hanzi_from_pinyin')
            ->set('answers.0.hanzi', '妳')
            ->set('answers.0.arti', 'kamu')
            ->call('checkAnswers')
            ->assertSet('results.0.status', 'wrong')
            ->assertSee('Hanzi: 你', false);
    }

    public function test_blank_rows_are_counted_separately_from_wrong(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeWord(['hanzi' => "字{$i}", 'pinyin' => "zi{$i}", 'meaning_id' => "arti{$i}"]);
        }
        $user = $this->onboardedUser();

        $component = Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('modeSoal', 'hanzi_from_pinyin')
            ->set('urutan', 'sequential')
            ->set('jumlah', '20');

        foreach (range(3, 19) as $i) {
            $component->set("answers.{$i}.hanzi", "字{$i}");
            $component->set("answers.{$i}.arti", "arti{$i}");
        }

        $component->call('checkAnswers');

        $component->assertSet('summary.benar', 17)
            ->assertSet('summary.salah', 0)
            ->assertSet('summary.kosong', 3);
    }

    public function test_all_option_uses_the_full_pool_without_hardcoding(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->makeWord(['hanzi' => "字{$i}", 'pinyin' => "zi{$i}"]);
        }
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('jumlah', 'all')
            ->assertCount('questions', 7);
    }

    public function test_reset_clears_answers_but_keeps_the_same_questions(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeWord(['hanzi' => "字{$i}", 'pinyin' => "zi{$i}"]);
        }
        $user = $this->onboardedUser();

        $component = Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('jumlah', '5');

        $questionsBefore = $component->get('questions');

        $component->set('answers.0.hanzi', 'x')
            ->call('checkAnswers')
            ->call('resetAnswers')
            ->assertSet('answers.0.hanzi', '')
            ->assertSet('checked', false);

        $this->assertSame($questionsBefore, $component->get('questions'));
    }

    public function test_changing_mode_regenerates_questions_and_clears_answers(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeWord(['hanzi' => "字{$i}", 'pinyin' => "zi{$i}"]);
        }
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(LatihanIsian::class)
            ->set('jumlah', '5')
            ->set('answers.0.hanzi', 'x')
            ->set('modeSoal', 'pinyin_from_hanzi')
            ->assertSet('answers.0.hanzi', '')
            ->assertSet('checked', false);
    }
}
