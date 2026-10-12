<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SidebarGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->seed(ReferenceDataSeeder::class);
    }

    private function user(): User
    {
        return User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    /** The desktop sidebar's <nav aria-label="Navigasi utama"> ... </nav>. */
    private function sidebar(string $url): string
    {
        $html = $this->actingAs($this->user())->get($url)->assertOk()->getContent();
        $nav = substr($html, strpos($html, 'aria-label="Navigasi utama"'));

        return substr($nav, 0, strpos($nav, '</nav>'));
    }

    /** The markup of one group: from its heading/summary id to the end of its list. */
    private function group(string $nav, string $key): string
    {
        $start = strpos($nav, 'id="nav-'.$key.'"');
        $this->assertNotFalse($start, "group {$key}");
        $rest = substr($nav, $start);

        return substr($rest, 0, strpos($rest, '</ul>'));
    }

    /** Whether the <details> right before a group's summary carries the open attribute. */
    private function isOpen(string $nav, string $key): bool
    {
        $before = substr($nav, 0, strpos($nav, 'id="nav-'.$key.'"'));
        $details = substr($before, strrpos($before, '<details'));

        return (bool) preg_match('/<details\s+open\b/', $details);
    }

    public function test_three_titled_groups_with_belajar_always_open_and_the_rest_as_details(): void
    {
        $nav = $this->sidebar('/dashboard');

        $this->assertMatchesRegularExpression('#<h2 id="nav-belajar"[^>]*>Belajar</h2>\s*<ul aria-labelledby="nav-belajar"#', $nav);
        $this->assertMatchesRegularExpression('#<details[^>]*>\s*<summary id="nav-latihan"[^>]*>Latihan</summary>\s*<ul aria-labelledby="nav-latihan"#', $nav);
        $this->assertMatchesRegularExpression('#<details[^>]*>\s*<summary id="nav-lainnya"[^>]*>Lainnya</summary>\s*<ul aria-labelledby="nav-lainnya"#', $nav);
        $this->assertSame(2, substr_count($nav, '<details'));
        $this->assertStringContainsString('min-h-[44px]', $nav);

        foreach (['belajar' => ['Hari Ini', 'Ngobrol', 'Ucapan', 'Kata bicara'], 'latihan' => ['Kartu', 'Game', 'Isian', 'Ujian'], 'lainnya' => ['Progress', 'Kamus', 'Bantuan']] as $key => $labels) {
            $group = $this->group($nav, $key);
            $this->assertSame($labels, array_map('trim', preg_match_all('#<span>([^<]+)</span>#', $group, $m) ? $m[1] : []), $key);
        }
    }

    public function test_collapsed_groups_open_only_when_the_current_page_is_inside(): void
    {
        $cases = [
            '/dashboard' => ['latihan' => false, 'lainnya' => false],
            '/game' => ['latihan' => true, 'lainnya' => false],
            '/cards' => ['latihan' => true, 'lainnya' => false],
            '/kamus' => ['latihan' => false, 'lainnya' => true],
            '/progress' => ['latihan' => false, 'lainnya' => true],
        ];

        foreach ($cases as $url => $expected) {
            $nav = $this->sidebar($url);
            foreach ($expected as $key => $open) {
                $this->assertSame($open, $this->isOpen($nav, $key), "{$url} {$key}");
            }
        }
    }

    public function test_current_page_link_has_aria_current_and_game_stays_current_on_game_pages(): void
    {
        foreach (['/kamus' => 'kamus', '/progress' => 'progress', '/cards' => 'cards.index', '/game' => 'game', '/game/dengar-pilih' => 'game', '/game/pemburu-kesalahan' => 'game'] as $url => $route) {
            $nav = $this->sidebar($url);
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route($route), '#').'"\s+aria-current="page"#', $nav, $url);
            $this->assertSame(1, substr_count($nav, 'aria-current="page"'), $url);
        }
    }

    public function test_icons_are_hidden_and_all_old_routes_are_still_linked(): void
    {
        $nav = $this->sidebar('/dashboard');

        $this->assertSame(substr_count($nav, '<a href='), substr_count($nav, '<span aria-hidden="true">'));
        foreach (['dashboard', 'conversation', 'pronunciation-check', 'kata-bicara', 'cards.index', 'game', 'latihan-isian', 'ujian', 'progress', 'kamus', 'bantuan'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $nav, $route);
        }
    }

    public function test_scan_link_only_when_the_flag_is_on_and_inside_belajar(): void
    {
        $this->assertStringNotContainsString('Raba lalu dengar', $this->sidebar('/dashboard'));

        config(['scan.enabled' => true]);
        $nav = $this->sidebar('/dashboard');
        $this->assertStringContainsString('Raba lalu dengar', $this->group($nav, 'belajar'));
    }

    public function test_mobile_more_panel_uses_the_same_group_titles(): void
    {
        $html = $this->actingAs($this->user())->get('/dashboard')->getContent();
        $panel = substr($html, strpos($html, 'id="judul-lainnya"'));

        $this->assertMatchesRegularExpression('#<h3 id="lainnya-belajar"[^>]*>Belajar</h3>#', $panel);
        $this->assertMatchesRegularExpression('#<h3 id="lainnya-latihan"[^>]*>Latihan</h3>#', $panel);
        $this->assertMatchesRegularExpression('#<h3 id="lainnya-lainnya"[^>]*>Lainnya</h3>#', $panel);
        // Bottom-bar items are not repeated in the panel.
        $this->assertStringNotContainsString('href="'.route('kamus').'"', $panel);
        $this->assertStringContainsString('href="'.route('ujian').'"', $panel);
    }
}
