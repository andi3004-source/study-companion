<?php

namespace Tests\Feature;

use App\Livewire\Games\ErrorHunter;
use App\Livewire\ProgressOverview;
use App\Models\ConversationSession;
use App\Models\GameAttempt;
use App\Models\User;
use App\Services\ProgressStats;
use App\Support\ErrorGameItems;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameAttemptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ErrorGameItems::flush();
        config(['game.items_path' => base_path('tests/Fixtures/kesalahan_game.json')]);
    }

    private function user(): User
    {
        return User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    private function attempt(User $user, string $kategori, bool $benar, $at = null): GameAttempt
    {
        return GameAttempt::forceCreate([
            'user_id' => $user->id, 'item_id' => '1', 'kategori' => $kategori, 'benar' => $benar, 'created_at' => $at ?? now(),
        ]);
    }

    public function test_every_answer_is_stored_for_its_own_user(): void
    {
        $alice = $this->user();
        $bob = $this->user();

        $game = Livewire::actingAs($alice)->test(ErrorHunter::class);
        $first = ErrorGameItems::find($game->get('questions')[0]['id']);
        $game->call('answer', $first['kategori'])->call('next');

        $second = ErrorGameItems::find($game->get('questions')[1]['id']);
        $wrong = collect($game->get('questions')[1]['options'])->first(fn ($o) => $o !== $second['kategori']);
        $game->call('answer', $wrong)
            ->call('answer', $second['kategori']); // second answer to the same question: ignored, not stored

        $attempts = GameAttempt::where('user_id', $alice->id)->orderBy('id')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame($first['id'], $attempts[0]->item_id); // stored as a string, read back as int
        $this->assertTrue($attempts[0]->benar);
        $this->assertSame($second['id'], $attempts[1]->item_id);
        $this->assertFalse($attempts[1]->benar);
        // The question's own category, not the one the player picked.
        $this->assertSame($second['kategori'], $attempts[1]->kategori);

        $this->assertSame(0, GameAttempt::where('user_id', $bob->id)->count());
    }

    public function test_weak_categories_count_only_wrong_answers_ordered_by_count_then_recency(): void
    {
        $user = $this->user();
        $this->attempt($user, 'bu_atau_mei', false, now()->subDays(3));
        $this->attempt($user, 'bu_atau_mei', false, now()->subDays(2));
        $this->attempt($user, 'kata_penggolong', false, now()->subDays(5));
        $this->attempt($user, 'urutan_kata', false, now()->subDay()); // ties with kata_penggolong, but more recent
        $this->attempt($user, 'shi_dengan_kata_sifat', true);
        $this->attempt($user, 'shi_dengan_kata_sifat', true);
        $this->attempt($user, 'kosakata', false, now()->subDays(6));

        $weak = app(ProgressStats::class)->gameWeakCategories($user);

        $this->assertSame([
            ['kategori' => 'bu_atau_mei', 'label' => '不 atau 没', 'jumlah' => 2],
            ['kategori' => 'urutan_kata', 'label' => 'Urutan kata', 'jumlah' => 1],
            ['kategori' => 'kata_penggolong', 'label' => 'Kata penggolong', 'jumlah' => 1],
        ], $weak);
    }

    public function test_weak_categories_respect_the_day_limit(): void
    {
        $user = $this->user();
        $this->attempt($user, 'nada', false, now()->subDays(31));
        $this->attempt($user, 'urutan_kata', false, now()->subDays(29));

        $this->assertSame(['urutan_kata'], array_column(app(ProgressStats::class)->gameWeakCategories($user), 'kategori'));
        $this->assertSame([], app(ProgressStats::class)->gameWeakCategories($user, days: 7));
    }

    public function test_weak_categories_are_per_user(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $this->attempt($alice, 'nada', false);
        $this->attempt($bob, 'urutan_kata', false);
        $this->attempt($bob, 'urutan_kata', false);

        $this->assertSame(['nada'], array_column(app(ProgressStats::class)->gameWeakCategories($alice), 'kategori'));
        $this->assertSame(['urutan_kata'], array_column(app(ProgressStats::class)->gameWeakCategories($bob), 'kategori'));
    }

    public function test_game_and_conversation_stats_never_mix(): void
    {
        $user = $this->user();
        $this->attempt($user, 'kata_penggolong', false);
        ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering',
            'started_at' => now(), 'ended_at' => now(), 'analyzed_at' => now(),
            'error_analysis' => ['items' => [['kategori' => 'bu_atau_mei', 'contoh' => '我不有钱', 'koreksi' => '我没有钱', 'penjelasan' => '']], 'catatan' => null],
        ]);

        $stats = app(ProgressStats::class);

        $this->assertSame(['kata_penggolong'], array_column($stats->gameWeakCategories($user), 'kategori'));
        $this->assertSame(['bu_atau_mei'], array_column($stats->topErrorCategories($user), 'kategori'));
    }

    public function test_progress_shows_the_game_card_with_data_and_when_empty(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $empty = $this->user();
        Livewire::actingAs($empty)->test(ProgressOverview::class)
            ->assertSee('Kategori yang sering meleset di game')
            ->assertSee('Belum ada jawaban salah di game dalam 30 hari terakhir.');

        $user = $this->user();
        $this->attempt($user, 'bu_atau_mei', false);
        $this->attempt($user, 'bu_atau_mei', false);

        Livewire::actingAs($user)->test(ProgressOverview::class)
            ->assertSeeHtmlInOrder(['Kategori yang sering meleset di game', '不 atau 没', '2× salah'])
            ->assertDontSee('Belum ada jawaban salah di game');
    }
}
