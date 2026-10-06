<?php

namespace Tests\Feature;

use App\Livewire\Kamus\Search;
use App\Models\MandarinHskWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClearUnreliableTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private function word(string $hanzi, string $meaningEn, ?string $meaningId, ?string $dataStatus = null): MandarinHskWord
    {
        return MandarinHskWord::create([
            'hanzi' => $hanzi,
            'pinyin' => 'p'.uniqid(),
            'meaning_en' => $meaningEn,
            'meaning_id' => $meaningId,
            'data_status' => $dataStatus,
            'hsk_level_old' => 2,
        ]);
    }

    public function test_dry_run_lists_rows_without_writing_anything(): void
    {
        $ye = $this->word('也', 'surname Ye', 'nama keluarga Ye');

        $this->artisan('hsk:clear-unreliable-translations', ['--dry-run' => true])
            ->expectsOutputToContain('nama keluarga Ye')
            ->expectsOutputToContain('1 row(s) would be cleared. Dry run: nothing written.')
            ->assertSuccessful();

        $this->assertSame('nama keluarga Ye', $ye->fresh()->meaning_id);
    }

    public function test_clears_only_uncorrected_rows_whose_first_sense_is_surname_or_slang(): void
    {
        $ye = $this->word('也', 'surname Ye', 'nama keluarga Ye');
        $ma = $this->word('马', 'surname Ma; abbr. for Malaysia', 'nama keluarga Ma');
        $slang = $this->word('测试', '(slang) to cheat', 'selingkuh');
        $corrected = $this->word('高', 'surname Gao', 'tinggi', 'corrected_draft');
        $airport = $this->word('机场', 'airport; airfield; (slang) service provider', 'bandara');
        $green = $this->word('绿', 'green; (slang) to cheat on', 'hijau');

        $this->artisan('hsk:clear-unreliable-translations')
            ->expectsOutputToContain('3 row(s) cleared.')
            ->assertSuccessful();

        $this->assertNull($ye->fresh()->meaning_id);
        $this->assertNull($ma->fresh()->meaning_id);
        $this->assertNull($slang->fresh()->meaning_id);
        $this->assertSame('tinggi', $corrected->fresh()->meaning_id);
        $this->assertSame('bandara', $airport->fresh()->meaning_id);
        $this->assertSame('hijau', $green->fresh()->meaning_id);
    }

    public function test_is_idempotent(): void
    {
        $this->word('也', 'surname Ye', 'nama keluarga Ye');

        $this->artisan('hsk:clear-unreliable-translations')->assertSuccessful();

        $this->artisan('hsk:clear-unreliable-translations')
            ->expectsOutputToContain('Nothing to clear.')
            ->assertSuccessful();
    }

    public function test_cleared_row_shows_meaning_not_available_in_kamus(): void
    {
        $this->word('测试也', 'surname Ye', 'nama keluarga Ye');

        $this->artisan('hsk:clear-unreliable-translations')->assertSuccessful();

        $user = User::factory()->create(['start_date' => now()->toDateString(), 'track' => 'both', 'onboarded' => true]);

        Livewire::actingAs($user)->test(Search::class)
            ->set('query', '测试也')
            ->assertSee('Arti belum tersedia')
            ->assertDontSee('nama keluarga Ye');
    }
}
