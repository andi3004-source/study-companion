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
