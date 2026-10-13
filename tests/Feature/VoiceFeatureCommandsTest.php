<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature-specific voice commands (resources/js/voice/adapters.js). PHPUnit
 * doesn't run the JS, so these check the page markers the adapters read and
 * the adapter source: every command presses an existing control or fills an
 * existing field, so each feature's own rules (and server validation) apply.
 */
class VoiceFeatureCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->seed(ReferenceDataSeeder::class);
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    private function page(string $url): string
    {
        $user = User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);

        return $this->actingAs($user)->get($url)->assertOk()->getContent();
    }

    /** JS source with comments removed, so tests only look at code. */
    private function js(string $file = 'voice/adapters.js'): string
    {
        return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents(resource_path('js/'.$file)));
    }

    /** The source of one registered adapter, up to the next registration. */
    private function adapter(string $name): string
    {
        $js = $this->js();
        $start = strpos($js, "VoiceMode.register(\n    '{$name}'") ?: strpos($js, "VoiceMode.register('{$name}'");
        $this->assertNotFalse($start, "adapter {$name}");
        $next = strpos($js, 'VoiceMode.register(', $start + 20);

        return substr($js, $start, $next === false ? null : $next - $start);
    }

    public function test_engine_can_capture_one_answer_in_another_language(): void
    {
        $engine = $this->js('voice/engine.js');

        $this->assertStringContainsString("rec.lang = 'id-ID';", $engine);
        $this->assertStringContainsString('if (this.capture?.lang) rec.lang = this.capture.lang;', $engine);
        $this->assertMatchesRegularExpression('/captureNext\(lang, onText\) \{.*?normalize\(text\) === \'batal\'/s', $engine);
        // The captured answer bypasses command parsing and is used once.
        $this->assertMatchesRegularExpression('/if \(this\.capture\) \{\s*const \{ onText \} = this\.capture;\s*this\.capture = null;/', $engine);
        $this->assertMatchesRegularExpression('/stop\(message = .*?\) \{.*?this\.capture = null;/s', $engine);
    }

    public function test_shared_helpers_read_hanzi_in_mandarin_and_numbers_in_words(): void
    {
        $js = $this->js();

        $this->assertStringContainsString("lang: /\\p{Script=Han}/u.test(part) ? 'zh-CN' : 'id-ID'", $js);
        $this->assertStringContainsString("} else if (word === 'belas') {", $js);
        $this->assertStringContainsString("} else if (word === 'puluh') {", $js);
        // Emoji and arrows hidden from screen readers are not spoken either.
        $this->assertStringContainsString('clone.querySelectorAll(\'[aria-hidden="true"]\').forEach((n) => n.remove());', $js);
    }

    public function test_kamus_search_filters_and_results(): void
    {
        $html = $this->page('/kamus?query=ai');
        $kamus = $this->adapter('kamus');

        $this->assertStringContainsString('data-voice-page="kamus"', $html);
        $this->assertStringContainsString("fill(document.getElementById('cari-kamus')", $kamus);
        $this->assertStringContainsString('buttonNamed(/^tampilkan lebih$/i)', $kamus);
        $this->assertStringContainsString("root().querySelectorAll('[role=\"group\"] button')", $kamus);
        $this->assertStringContainsString("el.querySelector('button[aria-label^=\"Dengarkan\"]')?.click();", $kamus);
        foreach (['cari lalu katanya', 'lanjut', 'nomor hasil', 'bahasa mandarin', 'level HSK'] as $command) {
            $this->assertStringContainsString($command, $kamus, $command);
        }

        // Markers the adapter reads.
        $view = file_get_contents(resource_path('views/livewire/kamus/search.blade.php'));
        foreach (['data-hasil data-lang-code="{{ $entry->lang_code }}"', '<p data-kata', '<p data-bacaan', 'data-kosong'] as $marker) {
            $this->assertStringContainsString($marker, $view, $marker);
        }
        // The meaning line starts with a flag emoji; the adapter says the meaning, not "bendera".
        $this->assertStringContainsString(".replace(/[\\u{1F1E6}-\\u{1F1FF}]/gu, '')", $kamus);
    }

    public function test_ujian_picker_opens_modes_by_name(): void
    {
        $html = $this->page('/ujian');
        $picker = $this->adapter('ujian-picker');

        $this->assertStringContainsString('data-voice-page="ujian-picker"', $html);
        $this->assertStringContainsString('data-voice-start="Ujian Mandarin"', $html);
        $this->assertStringContainsString('data-voice-start="Ujian English"', $html);
        $this->assertStringContainsString('link.click();', $picker);
        $this->assertStringContainsString("hint: 'mulai, tes hari ini, mandarin, inggris, riwayat, kembali, berhenti'", $picker);
    }

    public function test_ujian_runner_answers_through_the_options_and_confirms_before_submitting(): void
    {
        $html = $this->page('/ujian/mandarin');
        $run = $this->adapter('ujian-run');

        $this->assertStringContainsString('data-voice-page="ujian-run"', $html);
        $this->assertMatchesRegularExpression('#<div data-soal class="[^"]*">\s*<p[^>]*>1\. <span data-pertanyaan>#', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);

        // Answers press the existing option buttons; hanzi in a question is read in Mandarin.
        $this->assertStringContainsString("q?.querySelectorAll('button[aria-pressed]')", $run);
        $this->assertStringContainsString('option.click();', $run);
        $this->assertStringContainsString("mixedSegments(text(q.querySelector('[data-pertanyaan]')))", $run);
        // Feature rules: no changes after submitting, and submitting always asks first.
        $this->assertStringContainsString("if (submitted()) return engine.tell('Ujian sudah dikumpulkan, jawaban tidak bisa diubah.", $run);
        $this->assertMatchesRegularExpression('/const collect = \(engine\) => \{.*?engine\.pending = \{\s*onYes: async \(\) => \{\s*submit\.click\(\);/s', $run);
        $this->assertStringContainsString('soal belum dijawab. ', $run);
        $this->assertStringContainsString('buttonNamed(/^kumpulkan jawaban$/i)', $run);
        // "Ulangi" stays "read again"; a new exam is "coba lagi", only after submitting.
        $this->assertStringContainsString('const retry = submitted() ? buttonNamed(/^ulangi$/i) : null;', $run);
    }

    public function test_ucapan_records_through_the_existing_button_and_reads_the_result(): void
    {
        $html = $this->page('/pronunciation-check');
        $ucapan = $this->adapter('ucapan');

        $this->assertStringContainsString('data-voice-page="ucapan"', $html);
        $view = file_get_contents(resource_path('views/livewire/pronunciation-check.blade.php'));
        $this->assertStringContainsString('data-saran data-lang-code="{{ $entry->lang_code }}"', $view);
        $this->assertStringContainsString('<div data-kata-terpilih', $view);

        // Recording presses "Rekam suara", then "Berhenti merekam"; the server judges the result.
        $this->assertMatchesRegularExpression('/const button = buttonNamed\(\/rekam suara\/i\);.*?button\.click\(\);\s*await wait\(RECORD_MS\);\s*buttonNamed\(\/berhenti merekam\/i\)\?\.click\(\);/s', $ucapan);
        $this->assertStringContainsString("await engine.tell('Setelah bunyi, ucapkan katanya.", $ucapan);
        $this->assertStringContainsString('await engine.beep();', $ucapan);
        // The adapter never opens the microphone itself.
        $this->assertStringNotContainsString('getUserMedia', $ucapan);
        $this->assertStringNotContainsString('MediaRecorder', $ucapan);
        foreach (['fill(field, cari[1]', "choose(document.getElementById('ucapan-bahasa')", 'buttonNamed(/coba lagi/i)', 'buttonNamed(/ganti kata|cari kata lain/i)'] as $part) {
            $this->assertStringContainsString($part, $ucapan, $part);
        }
    }

    public function test_bantuan_reads_sections_by_number(): void
    {
        $html = $this->page('/bantuan');
        $bantuan = $this->adapter('bantuan');

        $this->assertStringContainsString('data-voice-page="bantuan"', $html);
        $this->assertStringContainsString("root().querySelectorAll('section')", $bantuan);
        $this->assertStringContainsString('Katakan nomor bagian untuk mendengarnya.', $bantuan);
        // Bantuan lists the new feature commands.
        foreach (['"isi hanzi"', '"kumpulkan" (selalu ditanya dulu)', '"rekam" (setelah bunyi', '"bahasa mandarin"', '"jalur mandarin"'] as $phrase) {
            $this->assertStringContainsString($phrase, $html, $phrase);
        }
    }

    public function test_onboarding_fields_are_labelled_and_voice_fills_them(): void
    {
        $user = User::factory()->create(['onboarded' => false]);
        $html = $this->actingAs($user)->get('/onboarding')->assertOk()->getContent();
        $onboarding = $this->adapter('onboarding');

        $this->assertStringContainsString('data-voice-page="onboarding"', $html);
        // Label tied to the date field, radio group named by a legend, decorative glyph hidden.
        $this->assertStringContainsString('<label for="onboarding-tanggal"', $html);
        $this->assertStringContainsString('id="onboarding-tanggal"', $html);
        $this->assertMatchesRegularExpression('#<fieldset>\s*<legend[^>]*>Pilih jalur belajar</legend>#', $html);
        $this->assertStringContainsString('<div aria-hidden="true" class="w-14 h-14', $html);

        $this->assertStringContainsString('fill(dateField(), isoDate(day));', $onboarding);
        $this->assertStringContainsString('radio?.click();', $onboarding);
        $this->assertStringContainsString('buttonNamed(/^mulai belajar$/i)?.click();', $onboarding);
    }

    public function test_every_page_after_login_has_a_voice_adapter(): void
    {
        $views = collect(glob(resource_path('views/livewire/{,*/}*.blade.php'), GLOB_BRACE))
            ->reject(fn ($p) => str_contains($p, '/layout/') || str_contains($p, '/pages/') || str_contains($p, '/partials/') || str_contains($p, '/profile/') || str_contains($p, '/welcome/'));

        foreach ($views as $view) {
            preg_match('/data-voice-page="([a-z-]+)"/', file_get_contents($view), $m);
            $this->assertNotEmpty($m, basename($view).' has no data-voice-page');
            $this->assertMatchesRegularExpression("/VoiceMode\\.register\\(\\s*'".preg_quote($m[1], '/')."'/", $this->js(), $m[1]);
        }
    }

    public function test_latihan_isian_fills_the_existing_fields_and_hears_hanzi_in_mandarin(): void
    {
        $html = $this->page('/latihan-isian');
        $isian = $this->adapter('latihan-isian');

        $this->assertStringContainsString('data-voice-page="latihan-isian"', $html);
        $this->assertMatchesRegularExpression('#<tr data-soal [^>]*>\s*<td[^>]*>1</td>\s*<td data-petunjuk#', $html);
        $this->assertMatchesRegularExpression('#wire:model="answers\.0\.hanzi" data-isian="hanzi"#', $html);
        $this->assertStringContainsString('<td data-hasil-soal', $html);

        // Hanzi answers are recognised in Mandarin, pinyin in Indonesian; both go into the existing inputs.
        $this->assertStringContainsString("hanzi: { label: 'Hanzi', lang: 'zh-CN' }, pinyin: { label: 'Pinyin', lang: 'id-ID' }", $isian);
        $this->assertStringContainsString('engine.captureNext(FIELD[name].lang, (heard) => fillField(engine, field, heard));', $isian);
        $this->assertStringContainsString('fill(field, clean(value));', $isian);
        // Grading stays on the server (the existing button); reset asks first like the button does.
        $this->assertStringContainsString('buttonNamed(/analisis jawaban/i)?.click();', $isian);
        $this->assertStringContainsString('if (reset) await engine.activate(reset);', $isian);
        $this->assertMatchesRegularExpression('/const DANGEROUS = \/hapus\|reset\|/', $this->js('voice/engine.js'));
        // Settings change the existing selects.
        foreach (["'isian-mode'", "'isian-jumlah'", "'isian-urutan'"] as $select) {
            $this->assertStringContainsString($select, $isian, $select);
        }
    }
}
