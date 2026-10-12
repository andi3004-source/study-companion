<?php

namespace Tests\Feature;

use App\Livewire\Games\Hub;
use App\Models\User;
use App\Support\ErrorGameItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GameHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        ErrorGameItems::flush();
        config(['game.items_path' => base_path('tests/Fixtures/kesalahan_game.json')]);
    }

    private function user(): User
    {
        return User::factory()->create(['start_date' => now()->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    public function test_hub_lists_games_as_cards_with_status_and_named_links(): void
    {
        $html = $this->actingAs($this->user())->get('/game')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('#<h2[^>]*>Pemburu Kesalahan</h2>#', $html);
        $this->assertStringContainsString('Status: Draf', $html);
        $this->assertStringContainsString('Cara bermain: keyboard dan suara', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('game.error-hunter'), '#').'"[^>]*>Mulai Pemburu Kesalahan</a>#', $html);
    }

    public function test_missing_question_file_is_explained_as_status_not_error(): void
    {
        config(['game.items_path' => base_path('tests/Fixtures/does_not_exist.json')]);
        ErrorGameItems::flush();

        Livewire::actingAs($this->user())->test(Hub::class)
            ->assertSeeHtml('<p role="status"')
            ->assertSee('Soal Pemburu Kesalahan belum tersedia karena berkas soalnya belum dipasang.')
            ->assertDontSee('Mulai Pemburu Kesalahan')
            ->assertDontSeeHtml('text-red');
    }

    public function test_error_hunter_links_back_to_the_hub_and_nav_marks_game_current(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/game/pemburu-kesalahan')->assertOk()->assertSee('Semua game')->assertSee(route('game'), false);
        $this->actingAs($user)->get('/game')->assertSee('aria-current="page"', false);
    }

    public function test_game_nav_links_to_the_hub_in_sidebar_and_more_panel_and_stays_current_on_all_game_pages(): void
    {
        $user = $this->user();
        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        // Sidebar and the mobile "Lainnya" panel both link Game to the hub.
        $this->assertSame(2, substr_count($html, 'href="'.route('game').'"'));
        $this->assertStringNotContainsString('href="'.route('game.error-hunter').'"', $html);

        foreach (['/game', '/game/dengar-pilih', '/game/pemburu-kesalahan'] as $url) {
            $page = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('game'), '#').'"\s+aria-current="page"#', $page, $url);
        }

        // Pemburu Kesalahan stays reachable through its hub card.
        $this->actingAs($user)->get('/game')->assertSee('href="'.route('game.error-hunter').'"', false);
    }

    public function test_error_hunter_empty_state_is_a_status_message(): void
    {
        config(['game.items_path' => base_path('tests/Fixtures/does_not_exist.json')]);
        ErrorGameItems::flush();

        $this->actingAs($this->user())->get('/game/pemburu-kesalahan')
            ->assertOk()
            ->assertSee('Data game belum tersedia.')
            ->assertSee('Berkas soal Pemburu Kesalahan belum dipasang', false);
    }
}
