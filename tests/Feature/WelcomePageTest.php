<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    private function html(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    public function test_page_renders_all_sections(): void
    {
        $this->get('/')->assertOk()->assertSeeInOrder([
            'Lewati ke konten',
            'Latihan Mandarin dan Inggris lewat ngobrol',
            'Masalahnya', '0 dari 4.991', 'Sekitar 16%',
            'Cara kerja', 'Ngobrol', 'Analisis kesalahan', 'Latihan',
            'Fitur dan statusnya',
            'Aksesibilitas', 'belum', 'diuji dengan pengguna tunanetra',
            'Penggunaan AI dalam pengerjaan', 'Claude Code', 'Claude', 'Gemini', 'IBM Langflow dan Astra DB', 'IBM Bob', 'Peran tim',
            'Pertanyaan umum', 'Data apa yang disimpan?', 'Foto tidak disimpan.', 'Chrome atau Edge',
            'Bantuan',
        ]);
    }

    public function test_headings_are_in_order_with_a_single_h1(): void
    {
        $html = $this->html();

        $this->assertSame(1, substr_count($html, '<h1'));
        preg_match_all('/<h([1-6])\b/', $html, $matches);
        $levels = array_map('intval', $matches[1]);

        $this->assertSame(1, $levels[0]);
        for ($i = 1; $i < count($levels); $i++) {
            $this->assertLessThanOrEqual($levels[$i - 1] + 1, $levels[$i], 'Heading level skipped');
        }
    }

    public function test_feature_status_badges_are_text(): void
    {
        $html = $this->html();

        foreach (['Berjalan', 'Draf', 'Eksperimen'] as $status) {
            $this->assertStringContainsString("Status: {$status}", $html);
        }
    }

    public function test_hero_and_header_offer_login_and_register(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('login'), '#').'"[^>]*>Masuk atau daftar</a>#', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('register'), '#').'"[^>]*>Daftar</a>#', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('login'), '#').'"[^>]*>Masuk</a>#', $html);
        $this->assertStringContainsString('href="#cara-kerja"', $html);
        $this->assertStringContainsString('id="cara-kerja"', $html);
        $this->assertStringContainsString('href="#konten"', $html);
        $this->assertStringContainsString('href="'.route('bantuan').'"', $html);
    }

    public function test_no_forbidden_impact_claims(): void
    {
        $html = mb_strtolower($this->html());

        foreach (['membantu penyandang disabilitas', 'membantu tunanetra', 'terbukti', 'ramah disabilitas', 'sepenuhnya aksesibel'] as $claim) {
            $this->assertStringNotContainsString($claim, $html);
        }
    }

    public function test_no_ai_features_scripts_or_external_requests(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('wire:', $html);
        $this->assertStringNotContainsString('livewire', strtolower($html));
        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('fonts.googleapis', $html);
        $this->assertStringNotContainsString('fonts.bunny', $html);
        $this->assertStringNotContainsString('app.js', $html);
        $this->assertDoesNotMatchRegularExpression('#<(script|link|img|iframe)[^>]+(src|href)="https?://(?!'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost', '#').')#', $html);
        $this->assertStringNotContainsString('@import', file_get_contents(resource_path('css/welcome.css')));
    }

    public function test_login_page_link_back_to_home_works_for_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('href="/"', false);
        $this->get('/')->assertOk()->assertViewIs('welcome');
    }
}
