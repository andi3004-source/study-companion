<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BantuanPageTest extends TestCase
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

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/bantuan')->assertRedirect(route('login'));
    }

    public function test_bantuan_page_explains_keyboard_voice_and_display_settings(): void
    {
        $html = $this->actingAs($this->onboardedUser())->get('/bantuan')
            ->assertOk()
            ->assertSee('Memakai dengan keyboard dan pembaca layar')
            ->assertSee('Memakai input suara')
            ->assertSee('Chrome atau Edge')
            ->assertSee('Mengatur tampilan')
            ->getContent();

        foreach (['Tab', 'Shift</kbd>+<kbd>Tab', 'Enter', 'Shift</kbd>+<kbd>Enter', 'Escape'] as $key) {
            $this->assertStringContainsString("<kbd>{$key}</kbd>", $html);
        }
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_statement_texts_are_exact_and_no_disability_claim(): void
    {
        $this->actingAs($this->onboardedUser())->get('/bantuan')
            ->assertOk()
            ->assertSee('Belajar bahasa dengan keyboard dan suara. Dirancang agar mudah diakses.')
            ->assertSee('Pernyataan aksesibilitas (draf): kami berupaya mengikuti pedoman WCAG 2.2 AA. Aplikasi ini diuji oleh tim dengan pembaca layar Narator dan belum diuji dengan pengguna tunanetra. Masukan Anda sangat kami harapkan.')
            ->assertDontSee('membantu penyandang disabilitas');
    }

    public function test_bantuan_is_linked_from_navigation_and_footer(): void
    {
        $html = $this->actingAs($this->onboardedUser())->get(route('dashboard'))->assertOk()->getContent();
        $link = 'href="'.route('bantuan').'"';

        $nav = substr($html, strpos($html, 'aria-label="Navigasi utama"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));
        $this->assertStringContainsString($link, $nav);

        $footer = substr($html, strpos($html, '<footer'), strpos($html, '</footer>') - strpos($html, '<footer'));
        $this->assertStringContainsString($link, $footer);
        $this->assertStringContainsString('Belajar bahasa dengan keyboard dan suara. Dirancang agar mudah diakses.', $footer);
    }
}
