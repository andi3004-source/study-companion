<?php

namespace Tests\Feature;

use App\Livewire\ProgressOverview;
use App\Models\ConversationSession;
use App\Models\User;
use App\Services\ProgressStats;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TopErrorCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    private function item(string $kategori, string $contoh = 'x', string $koreksi = 'y'): array
    {
        return ['kategori' => $kategori, 'contoh' => $contoh, 'koreksi' => $koreksi, 'penjelasan' => '...'];
    }

    /** An analyzed (or, with $items = null, failed) Mandarin session. */
    private function analyzed(User $user, ?array $items, $analyzedAt = null, string $lang = 'mandarin'): ConversationSession
    {
        return ConversationSession::create([
            'user_id' => $user->id, 'lang' => $lang, 'scenario_key' => 'restaurant_ordering',
            'started_at' => now()->subHour(), 'ended_at' => now()->subHour(),
            'error_analysis' => $items === null ? null : ['items' => $items, 'catatan' => null],
            'analyzed_at' => $analyzedAt ?? now(),
        ]);
    }

    public function test_orders_by_count_and_limits_to_three_with_labels_and_latest_example(): void
    {
        $user = $this->user();

        $this->analyzed($user, [
            $this->item('bu_atau_mei', '我不有时间', '我没有时间'),
            $this->item('nada'),
            $this->item('kata_penggolong'),
        ], now()->subDays(2));
        $this->analyzed($user, [
            $this->item('bu_atau_mei', '我不有钱', '我没有钱'),
            $this->item('bu_atau_mei', '他不有书', '他没有书'),
            $this->item('shi_dengan_kata_sifat'),
            $this->item('nada'),
            $this->item('urutan_kata'),
        ], now()->subDay());

        $top = app(ProgressStats::class)->topErrorCategories($user);

        $this->assertSame(['bu_atau_mei', 'nada'], array_column(array_slice($top, 0, 2), 'kategori'));
        $this->assertSame([3, 2, 1], array_column($top, 'jumlah'));
        $this->assertCount(3, $top);
        // Third place is a 1-1-1 tie; the most recently seen one wins (newest session's items, in order).
        $this->assertSame('shi_dengan_kata_sifat', $top[2]['kategori']);

        $this->assertSame('不 atau 没', $top[0]['label']);
        // Latest example = from the newest session.
        $this->assertSame('我不有钱', $top[0]['contoh']);
        $this->assertSame('我没有钱', $top[0]['koreksi']);
    }

    public function test_only_counts_the_last_seven_days(): void
    {
        $user = $this->user();
        $this->analyzed($user, [$this->item('nada'), $this->item('nada')], now()->subDays(8));
        $this->analyzed($user, [$this->item('pelafalan')], now()->subDays(6));

        $top = app(ProgressStats::class)->topErrorCategories($user);

        $this->assertSame(['pelafalan'], array_column($top, 'kategori'));
    }

    public function test_only_counts_successfully_analyzed_mandarin_sessions(): void
    {
        $user = $this->user();
        $this->analyzed($user, null); // tried and failed
        ConversationSession::create([ // ended, not analyzed yet
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering',
            'started_at' => now(), 'ended_at' => now(),
            'error_analysis' => ['items' => [$this->item('nada')], 'catatan' => null],
        ]);
        $this->analyzed($user, [$this->item('urutan_kata')], lang: 'english');
        $this->analyzed($user, []); // analyzed, no mistakes
        $this->analyzed($user, [$this->item('kosakata')]);

        $top = app(ProgressStats::class)->topErrorCategories($user);

        $this->assertSame([['kategori' => 'kosakata', 'label' => 'Pilihan kata', 'jumlah' => 1, 'contoh' => 'x', 'koreksi' => 'y']], $top);
    }

    public function test_never_mixes_users(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $this->analyzed($alice, [$this->item('nada')]);
        $this->analyzed($bob, [$this->item('urutan_kata'), $this->item('urutan_kata')]);

        $this->assertSame(['nada'], array_column(app(ProgressStats::class)->topErrorCategories($alice), 'kategori'));
        $this->assertSame(['urutan_kata'], array_column(app(ProgressStats::class)->topErrorCategories($bob), 'kategori'));
    }

    public function test_unknown_stored_category_counts_as_lainnya(): void
    {
        $user = $this->user();
        $this->analyzed($user, [$this->item('tata_bahasa')]);

        $this->assertSame('Lainnya', app(ProgressStats::class)->topErrorCategories($user)[0]['label']);
    }

    public function test_progress_page_shows_the_card_with_data(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $user = $this->user();
        $this->analyzed($user, [$this->item('bu_atau_mei', '我不有钱', '我没有钱'), $this->item('bu_atau_mei', '他不有书', '他没有书')]);

        Livewire::actingAs($user)->test(ProgressOverview::class)
            ->assertSee('Kesalahan terbanyakmu')
            ->assertSeeHtmlInOrder(['不 atau 没', '我不有钱', '我没有钱', '2×'])
            ->assertDontSee('Belum ada data kesalahan');
    }

    public function test_progress_page_shows_a_clear_empty_state(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $user = $this->user();

        Livewire::actingAs($user)->test(ProgressOverview::class)
            ->assertSee('Kesalahan terbanyakmu')
            ->assertSee('Belum ada data kesalahan dalam 7 hari terakhir');
    }
}
