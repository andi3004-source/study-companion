<?php

namespace Tests\Feature;

use App\Livewire\KataBicara;
use App\Models\User;
use App\Services\KataBicaraCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class KataBicaraTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Any outgoing HTTP call fails the test: the page must work fully offline.
        Http::preventStrayRequests();

        $this->catalogPath = sys_get_temp_dir().'/katabicara-'.uniqid().'.json';
        // Same keys as storage/app/seed-data/daftar_benda_tertutup.json.
        $tampil = ['mandarin' => true, 'inggris' => true, 'indonesia' => true];
        file_put_contents($this->catalogPath, json_encode([
            ['id' => 1, 'hanzi' => '杯子', 'pinyin' => 'bēizi', 'nama_untuk_prompt' => 'Gelas', 'arti_tampil' => 'gelas, cangkir', 'inggris' => 'glass', 'ipa_inggris' => '/ɡlæs/', 'cefr_level' => 'A1', 'ikut_prompt' => true, 'tampil' => $tampil],
            ['id' => 2, 'hanzi' => '椅子', 'pinyin' => 'yǐzi', 'nama_untuk_prompt' => 'Kursi', 'arti_tampil' => 'kursi', 'inggris' => 'chair', 'ipa_inggris' => '/tʃeər/', 'cefr_level' => 'A1', 'ikut_prompt' => true, 'tampil' => $tampil],
            ['id' => 3, 'hanzi' => '刀', 'pinyin' => 'dāo', 'nama_untuk_prompt' => 'Pisau', 'arti_tampil' => 'pisau', 'inggris' => 'knife', 'ipa_inggris' => '/naɪf/', 'cefr_level' => 'A2', 'ikut_prompt' => false, 'tampil' => $tampil],
            // No nama_untuk_prompt: the Indonesian name falls back to arti_tampil.
            ['id' => 4, 'hanzi' => '桌子', 'pinyin' => 'zhuōzi', 'nama_untuk_prompt' => '', 'arti_tampil' => 'Meja', 'inggris' => 'table', 'ipa_inggris' => '/ˈteɪbəl/', 'cefr_level' => 'A1', 'ikut_prompt' => true, 'tampil' => $tampil],
        ], JSON_UNESCAPED_UNICODE));
        config(['katabicara.path' => $this->catalogPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->catalogPath);
        parent::tearDown();
    }

    protected function onboardedUser(): User
    {
        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    public function test_route_requires_auth_and_is_linked_in_navigation(): void
    {
        $this->get('/kata-bicara')->assertRedirect(route('login'));

        $html = $this->actingAs($this->onboardedUser())->get('/kata-bicara')
            ->assertOk()
            ->assertSee('Ucapkan kata')
            ->getContent();

        $nav = substr($html, strpos($html, 'aria-label="Navigasi utama"'));
        $this->assertStringContainsString('href="'.route('kata-bicara').'"', substr($nav, 0, strpos($nav, '</nav>')));
        $this->assertStringContainsString('<label for="cari-kata"', $html);
        $this->assertStringContainsString('aria-describedby="petunjuk-cari-kata"', $html);
    }

    public function test_search_by_indonesian_english_and_hanzi(): void
    {
        foreach (['gelas', 'Glass', '杯子'] as $query) {
            Livewire::actingAs($this->onboardedUser())->test(KataBicara::class)
                ->set('query', $query)
                ->call('cari')
                ->assertSee('Gelas')
                ->assertSee('bēizi')
                ->assertSee('/ɡlæs/')
                ->assertDontSee('Belum ada di daftar kami');
        }
    }

    public function test_result_card_has_labelled_rows_lang_and_play_buttons(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(KataBicara::class)
            ->set('query', 'kursi')
            ->call('cari')
            ->assertSeeHtml('role="status" id="hasil-kata-bicara"')
            ->assertSeeHtml('<dt class="text-xs font-700 text-ink-soft">Indonesia</dt>')
            ->assertSeeHtml('<dt class="text-xs font-700 text-ink-soft">Inggris</dt>')
            ->assertSeeHtml('<dt class="text-xs font-700 text-ink-soft">Mandarin</dt>')
            ->assertSeeHtml('<span lang="id" class="text-ink">Kursi</span>')
            ->assertSeeHtml('<span lang="en" class="text-ink">chair</span>')
            ->assertSeeHtml('<span lang="zh-Hans" class="text-ink text-xl">椅子</span>')
            ->assertSee('Dengarkan Indonesia')
            ->assertSee('Dengarkan Inggris')
            ->assertSee('Dengarkan Mandarin')
            ->assertSeeHtml("'id-ID')")
            ->assertSeeHtml("'en-US')")
            ->assertSeeHtml("'zh-CN')");
    }

    public function test_unknown_word_and_ikut_prompt_false_are_not_found_without_guessing(): void
    {
        foreach (['lemari', 'pisau', 'gelasku'] as $query) {
            Livewire::actingAs($this->onboardedUser())->test(KataBicara::class)
                ->set('query', $query)
                ->call('cari')
                ->assertSee('Belum ada di daftar kami')
                ->assertSee('Kata yang tersedia, misalnya:')
                ->assertSeeHtml('<li lang="id">Gelas</li>')
                ->assertSeeHtml('<li lang="id">Kursi</li>')
                ->assertDontSee('Dengarkan Mandarin');
        }
    }

    public function test_indonesian_name_falls_back_to_arti_tampil(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(KataBicara::class)
            ->set('query', 'meja')
            ->call('cari')
            ->assertSeeHtml('<span lang="id" class="text-ink">Meja</span>')
            ->assertSeeHtml('<span lang="zh-Hans" class="text-ink text-xl">桌子</span>');
    }

    public function test_real_catalog_file_loads_and_is_searchable(): void
    {
        $path = storage_path('app/seed-data/daftar_benda_tertutup.json');
        if (! is_file($path)) {
            $this->markTestSkipped('daftar_benda_tertutup.json belum ada di storage/app/seed-data.');
        }
        config(['katabicara.path' => $path]);

        $catalog = new KataBicaraCatalog;
        $this->assertTrue($catalog->available(), 'Berkas katalog asli tidak bisa dibaca sebagai daftar entri.');
        $this->assertNotEmpty($catalog->entries(), 'Tidak ada entri dengan ikut_prompt = true.');

        foreach ($catalog->entries() as $entry) {
            $this->assertNotSame('', $entry['indonesia'], "Entri {$entry['id']} tanpa nama_untuk_prompt/arti_tampil.");
            $this->assertNotSame('', $entry['hanzi'], "Entri {$entry['id']} tanpa hanzi.");
        }

        $first = $catalog->entries()->first();
        foreach (array_filter([$first['indonesia'], $first['inggris'], $first['hanzi']]) as $query) {
            $this->assertContains($first, $catalog->search($query)->all(), "Pencarian '{$query}' tidak menemukan entri pertama.");
        }
    }

    public function test_missing_catalog_shows_friendly_message(): void
    {
        config(['katabicara.path' => sys_get_temp_dir().'/tidak-ada-'.uniqid().'.json']);

        $this->actingAs($this->onboardedUser())->get('/kata-bicara')
            ->assertOk()
            ->assertSee('Katalog belum tersedia');
    }
}
