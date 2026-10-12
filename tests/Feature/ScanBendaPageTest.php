<?php

namespace Tests\Feature;

use App\Livewire\ScanBenda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ScanBendaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['scan.enabled' => true]);
    }

    private function user(): User
    {
        return User::factory()->create(['onboarded' => true, 'start_date' => now()->toDateString(), 'track' => 'both']);
    }

    public function test_page_has_heading_instructions_and_warning(): void
    {
        $html = $this->actingAs($this->user())->get(route('scan.object'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Raba lalu dengar</h1>', $html);
        $this->assertStringContainsString('Pegang satu benda, arahkan ke kamera, lalu ambil foto. Foto hanya diproses dan tidak disimpan.', $html);
        $this->assertStringContainsString('Ini alat bantu belajar, bukan alat keselamatan. Jangan dipakai untuk obat atau benda berbahaya.', $html);
    }

    public function test_consent_checkbox_is_labelled_and_blocks_the_photo_button(): void
    {
        Livewire::actingAs($this->user())->test(ScanBenda::class)
            ->assertSeeHtml('<label for="scan-setuju"')
            ->assertSeeHtml('id="scan-setuju" x-model="setuju"')
            ->assertSeeHtml('aria-disabled="true" x-bind:aria-disabled="(!setuju || memproses) ? \'true\' : \'false\'"')
            ->assertSeeHtml('aria-describedby="scan-alasan"')
            ->assertSee('Tombol Ambil foto aktif setelah kotak persetujuan dicentang.')
            ->assertSee('Ambil foto');
    }

    public function test_camera_input_uses_the_device_camera_without_livewire_uploads(): void
    {
        Livewire::actingAs($this->user())->test(ScanBenda::class)
            ->assertSeeHtml('type="file" accept="image/*" capture="environment"')
            ->assertDontSeeHtml('wire:model')
            ->assertSeeHtml('x-data="scanBenda(')
            ->assertSeeHtml('scan-benda\/kenali');
    }

    public function test_status_region_and_result_card_markup(): void
    {
        Livewire::actingAs($this->user())->test(ScanBenda::class)
            ->assertSeeHtml('role="status" aria-live="polite"')
            ->assertSeeHtml('<dd lang="id"')
            ->assertSeeHtml('<dd lang="en"')
            ->assertSeeHtml('<dd lang="zh-Hans"')
            ->assertSee('Pinyin')
            ->assertSee('IPA')
            ->assertSeeHtml('<strong>Bentuk:</strong>')
            ->assertSee('Perkiraan dari foto, mungkin tidak persis dengan perabaan')
            ->assertSee('Saya belum yakin. Mau foto lagi?')
            ->assertSeeHtml('x-ref="judulHasil" tabindex="-1"')
            ->assertSeeHtml('x-ref="galat" tabindex="-1"');
    }

    public function test_listen_and_retake_buttons(): void
    {
        Livewire::actingAs($this->user())->test(ScanBenda::class)
            ->assertSeeHtml('x-on:click="dengarSemua()"')
            ->assertSee('Dengarkan semua')
            ->assertSee('Dengarkan Indonesia')
            ->assertSee('Dengarkan Inggris')
            ->assertSee('Dengarkan Mandarin')
            ->assertSee('Foto lagi');
    }

    public function test_autoplay_is_an_opt_in_checkbox_off_by_default(): void
    {
        Livewire::actingAs($this->user())->test(ScanBenda::class)
            ->assertSeeHtml('<label for="scan-putar-otomatis"')
            ->assertSeeHtml('id="scan-putar-otomatis" x-model="putarOtomatis" x-on:change="simpanPutarOtomatis()"')
            ->assertSee('Putar suara otomatis')
            ->assertDontSeeHtml('id="scan-putar-otomatis" checked');

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('putarOtomatis: !!saved.putarOtomatis', $js);
        $this->assertStringContainsString("localStorage.setItem('scanBenda'", $js);
        // Autoplay needs the opt-in, a certain result, and screen reader mode off.
        $this->assertStringContainsString("if (this.putarOtomatis && data.jenis === 'pasti' && !window.screenReaderMode()) this.dengarSemua();", $js);
    }

    public function test_nav_link_shows_only_when_enabled(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get(route('progress'))->assertSee('Raba lalu dengar')->assertSee(route('scan.object'), false);

        config(['scan.enabled' => false]);
        $this->actingAs($user)->get(route('progress'))->assertDontSee('Raba lalu dengar')->assertDontSee(route('scan.object'), false);
    }

    public function test_disabled_flag_gives_404(): void
    {
        config(['scan.enabled' => false]);

        $this->actingAs($this->user())->get('/scan-benda')->assertNotFound();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/scan-benda')->assertRedirect(route('login'));
    }
}
