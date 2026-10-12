<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceModeDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bantuan_lists_voice_commands_and_honest_limits(): void
    {
        $user = User::factory()->create(['start_date' => now()->toDateString(), 'track' => 'both', 'onboarded' => true]);

        $this->actingAs($user)->get('/bantuan')->assertOk()
            ->assertSeeInOrder(['Mode suara', 'Perintah', 'daftar tindakan', 'selesai isi', 'Batas'])
            ->assertSee('Pembaca layar dan keyboard tetap jalur utama.')
            ->assertSee('Hanya berjalan di Chrome atau Edge dan butuh internet.')
            ->assertSee('Suara Mandarin harus terpasang di perangkat')
            ->assertSee('mikrofon perlu satu ketukan atau Enter')
            ->assertSee('Belum diuji dengan pengguna tunanetra.')
            ->assertDontSee('membantu penyandang disabilitas');
    }

    public function test_readme_documents_voice_mode_honestly(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $section = substr($readme, strpos($readme, '## Mode suara'));
        $section = substr($section, 0, strpos($section, "\n## ", 3));

        foreach (['Chrome atau Edge', 'butuh internet', 'penyedia', 'Akurasi bervariasi', 'suara Mandarin harus terpasang', 'satu', 'Belum diuji dengan pengguna', 'Pembaca layar tetap jalur utama'] as $phrase) {
            $this->assertStringContainsString($phrase, $section, $phrase);
        }
    }
}
