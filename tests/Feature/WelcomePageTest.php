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
            'Belajar bahasa dengan keyboard dan suara.',
            'Banyak kesalahan kecil, dampak besar.', '0 / 4.991', '~16%',
            'Mudah, terstruktur, efektif.', 'Dengarkan / Ketik', 'Analisis', 'Belajar',
            'Lebih dari sekadar koreksi.',
            'Rasakan pengalamannya sekarang.',
            'Untuk semua orang.', 'belum', 'diuji dengan pengguna tunanetra',
            'Transparan, bukan janji kosong.', 'Berjalan', 'Draf', 'Eksperimen', 'Rencana',
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

        // The pill shows the word; screen readers also hear "Status:" in front of it.
        foreach (['Berjalan', 'Draf', 'Eksperimen'] as $status) {
            $this->assertStringContainsString("<span class=\"sr-only\">Status: </span>{$status}</span>", $html);
        }
    }

    public function test_each_feature_has_the_agreed_status(): void
    {
        $html = $this->html();

        foreach ([
            'Percakapan dengan AI' => 'Berjalan',
            'Analisis kesalahan' => 'Eksperimen',
            'Cek Pengucapan' => 'Berjalan',
            'Arti Indonesia untuk kosakata HSK' => 'Draf',
            'Game Pemburu Kesalahan' => 'Draf',
            'Kata bicara' => 'Draf',
        ] as $fitur => $status) {
            $this->assertMatchesRegularExpression('#<h3[^>]*>'.preg_quote($fitur, '#').'</h3>\s*<span[^>]*data-pil><span class="sr-only">Status: </span>'.$status.'</span>#', $html, $fitur);
        }
    }

    public function test_ai_tool_roles_use_the_agreed_wording(): void
    {
        $this->get('/')
            ->assertSee('Asisten pengembangan di editor (peran terbatas).')
            ->assertSee('basis pengetahuan untuk RAG (pola kesalahan dan glosarium)')
            ->assertSee('Tim menentukan arah dan keputusan desain, mengaudit data, meninjau kode, menguji, dan memverifikasi hasil.')
            ->assertDontSee('menyambungkan aplikasi ke Langflow')
            ->assertDontSee('penyimpan data alur');
    }

    public function test_hero_and_header_offer_login_and_register(): void
    {
        $html = $this->html();

        // The hero button ends with a decorative arrow icon (aria-hidden SVG).
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('login'), '#').'"[^>]*>Masuk atau daftar<svg[^>]*aria-hidden="true"#', $html);
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
