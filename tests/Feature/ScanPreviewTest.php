<?php

namespace Tests\Feature;

use App\Livewire\ScanBenda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class ScanPreviewTest extends TestCase
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

    /** The scanBenda() component source plus the helpers it uses (JS is not run by PHPUnit). */
    private function scanJs(): string
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $start = strpos($js, 'async function compressPhoto');
        $end = strpos($js, 'window.gameSpeechRate');

        return substr($js, $start, $end - $start);
    }

    /** Body of one method inside scanBenda(), e.g. "fotoLagi() {". */
    private function method(string $signature): string
    {
        $js = $this->scanJs();
        $start = strpos($js, $signature);
        $this->assertNotFalse($start, $signature);

        return substr($js, $start, strpos($js, "\n        },", $start) - $start);
    }

    public function test_preview_markup_figure_img_alt_figcaption_and_labelled_toggle(): void
    {
        Livewire::actingAs($this->user())->test(ScanBenda::class)
            ->assertSeeHtml('<template x-if="fotoUrl && tampilkanFoto">')
            ->assertSeeHtml('<figure')
            ->assertSeeHtml('<img x-bind:src="fotoUrl" alt="Foto benda yang kamu ambil"')
            ->assertSeeHtml('<figcaption')
            ->assertSee('Foto yang diambil. Hanya tampil di layar ini; tidak disimpan.')
            ->assertSeeHtml('max-h-[320px]')
            ->assertSeeHtml('<label for="scan-tampilkan-foto"')
            ->assertSeeHtml('id="scan-tampilkan-foto" x-model="tampilkanFoto" x-on:change="ubahTampilkanFoto()"')
            ->assertSee('Tampilkan foto di layar')
            // The preview never replaces the status announcement.
            ->assertSeeHtml('<div role="status" aria-live="polite" class="text-ink" x-text="status"></div>');
    }

    public function test_preview_comes_before_the_result_in_the_markup(): void
    {
        $html = $this->actingAs($this->user())->get(route('scan.object'))->getContent();

        $this->assertLessThan(strpos($html, 'id="scan-judul-hasil"'), strpos($html, '<figure'));
    }

    public function test_object_url_is_created_from_the_compressed_blob_and_revoked_on_every_exit_path(): void
    {
        $js = $this->scanJs();

        // The preview uses the same ~1024 px Blob that is sent.
        $this->assertStringContainsString('canvas.toBlob(', $js);
        $this->assertStringContainsString('if (this.tampilkanFoto) this.fotoUrl = URL.createObjectURL(foto);', $js);
        $this->assertStringContainsString('const image = await blobToDataUrl(foto);', $js);

        $this->assertStringContainsString('URL.revokeObjectURL(this.fotoUrl)', $this->method('lepasFoto() {'));
        $this->assertStringContainsString('this.lepasFoto();', $this->method('fotoLagi() {'));           // "Foto lagi"
        $this->assertStringContainsString('this.lepasFoto();', $this->method('ubahTampilkanFoto() {'));  // preview switched off
        $this->assertStringContainsString('this.lepasFoto();', $this->method('destroy() {'));

        $kirim = $this->method('async kirim(event) {');
        $this->assertLessThan(strpos($kirim, 'URL.createObjectURL'), strpos($kirim, 'this.lepasFoto();'));    // new photo
        $this->assertStringContainsString("} catch (e) {\n                this.lepasFoto();", $kirim);           // error

        $init = $this->method('init() {');
        $this->assertStringContainsString("window.addEventListener('pagehide', this.lepasSaatPergi);", $init);
        $this->assertStringContainsString("document.addEventListener('livewire:navigating', this.lepasSaatPergi);", $init);
    }

    public function test_photo_is_never_written_to_browser_storage(): void
    {
        // Code only: the doc comment names these APIs to say they are not used.
        $js = preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $this->scanJs());

        $this->assertStringNotContainsString('sessionStorage', $js);
        $this->assertStringNotContainsStringIgnoringCase('indexedDB', $js);
        $this->assertSame(1, substr_count($js, 'localStorage.setItem('));
        $this->assertStringContainsString("localStorage.setItem('scanBenda', JSON.stringify({ putarOtomatis: this.putarOtomatis, tampilkanFoto: this.tampilkanFoto }));", $js);
        $this->assertStringContainsString('tampilkanFoto: saved.tampilkanFoto !== false', $js); // on by default
    }

    public function test_livewire_component_holds_no_image_data(): void
    {
        $public = array_map(
            fn (ReflectionProperty $p) => $p->getName(),
            array_filter((new ReflectionClass(ScanBenda::class))->getProperties(ReflectionProperty::IS_PUBLIC), fn ($p) => $p->getDeclaringClass()->getName() === ScanBenda::class)
        );

        $this->assertSame([], $public);
        $this->assertStringNotContainsString('WithFileUploads', file_get_contents(app_path('Livewire/ScanBenda.php')));
    }
}
