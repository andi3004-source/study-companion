<?php

namespace Tests\Unit;

use App\Support\VoiceCommandParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VoiceCommandParserTest extends TestCase
{
    /** @return array<string, array{string, string, ?string, ?int}> transcript, type, target, index */
    public static function cases(): array
    {
        return [
            // Every page.
            'hari ini' => ['Hari ini', 'navigate', 'dashboard', null],
            'dashboard' => ['buka dashboard', 'navigate', 'dashboard', null],
            'kartu' => ['Kartu.', 'navigate', 'cards.index', null],
            'kamus' => ['ke kamus', 'navigate', 'kamus', null],
            'ujian' => ['ujian', 'navigate', 'ujian', null],
            'isian' => ['buka isian', 'navigate', 'latihan-isian', null],
            'game' => ['Game!', 'navigate', 'game', null],
            'pemburu kesalahan' => ['pemburu kesalahan', 'navigate', 'game.error-hunter', null],
            'dengar dan pilih' => ['Dengar dan pilih', 'navigate', 'game.listen-pick', null],
            'pilih yang benar (belum ada)' => ['pilih yang benar', 'not_available', null, null],
            'ngobrol' => ['ngobrol', 'navigate', 'conversation', null],
            'obrolan' => ['buka obrolan', 'navigate', 'conversation', null],
            'percakapan' => ['percakapan', 'navigate', 'conversation', null],
            'ucapan' => ['ucapan', 'navigate', 'pronunciation-check', null],
            'cek pengucapan' => ['Cek pengucapan', 'navigate', 'pronunciation-check', null],
            'kata bicara' => ['kata bicara', 'navigate', 'kata-bicara', null],
            'progress' => ['Progress', 'navigate', 'progress', null],
            'kemajuan' => ['tolong buka kemajuan', 'navigate', 'progress', null],
            'bantuan' => ['bantuan', 'navigate', 'bantuan', null],

            // Commands.
            'back' => ['kembali', 'back', null, null],
            'repeat' => ['Ulangi.', 'repeat', null, null],
            'read page' => ['baca halaman', 'read_page', null, null],
            'list actions' => ['daftar tindakan', 'list_actions', null, null],
            'more' => ['lanjut', 'more', null, null],
            'yes' => ['Ya', 'confirm_yes', null, null],
            'no' => ['tidak', 'confirm_no', null, null],
            'stop' => ['berhenti', 'stop', null, null],
            'help' => ['perintah', 'help', null, null],
            'dictate start' => ['isi', 'dictate_start', null, null],
            'dictate start ketik' => ['ketik', 'dictate_start', null, null],
            'dictate stop' => ['selesai isi', 'dictate_stop', null, null],

            // Numbers.
            'satu' => ['satu', 'choose_number', null, 0],
            'dua belas' => ['dua belas', 'choose_number', null, 11],
            'sebelas' => ['nomor sebelas', 'choose_number', null, 10],
            'digit' => ['3', 'choose_number', null, 2],
            'nomor tiga' => ['Nomor tiga.', 'choose_number', null, 2],
            'pilihan dua' => ['pilihan dua', 'choose_number', null, 1],
            'pilih empat' => ['pilih 4', 'choose_number', null, 3],
            'yang kedua' => ['yang kedua', 'choose_number', null, 1],
            'pertama' => ['yang pertama', 'choose_number', null, 0],
            'kedua belas' => ['kedua belas', 'choose_number', null, 11],
            'huruf a' => ['A', 'choose_number', null, 0],
            'pilihan d' => ['pilihan d', 'choose_number', null, 3],

            // Ambiguous, out of range, empty.
            'two numbers' => ['dua tiga', 'unknown', null, null],
            'two digits' => ['nomor 2 atau 3', 'unknown', null, null],
            'two pages' => ['kartu kamus', 'unknown', null, null],
            'page plus number' => ['kartu dua', 'unknown', null, null],
            'thirteen' => ['13', 'unknown', null, null],
            'tiga belas' => ['tiga belas', 'unknown', null, null],
            'zero' => ['nomor 0', 'unknown', null, null],
            'letter e' => ['pilihan e', 'unknown', null, null],
            'empty' => ['', 'unknown', null, null],
            'punctuation only' => ['?!.', 'unknown', null, null],
            'free sentence' => ['apa kabar hari ini', 'unknown', null, null],
        ];
    }

    #[DataProvider('cases')]
    public function test_parse(string $transcript, string $type, ?string $target, ?int $index): void
    {
        config(['scan.enabled' => false]);

        $this->assertSame(['type' => $type, 'target' => $target, 'index' => $index], VoiceCommandParser::parse($transcript));
    }

    public function test_scan_pages_depend_on_the_flag(): void
    {
        foreach (['raba lalu dengar', 'kamera', 'potret'] as $phrase) {
            config(['scan.enabled' => false]);
            $this->assertSame(['type' => 'not_available', 'target' => 'scan.object', 'index' => null], VoiceCommandParser::parse($phrase), $phrase);

            config(['scan.enabled' => true]);
            $this->assertSame(['type' => 'navigate', 'target' => 'scan.object', 'index' => null], VoiceCommandParser::parse($phrase), $phrase);
        }
    }

    public function test_every_navigate_target_is_a_real_route(): void
    {
        config(['scan.enabled' => true]);

        foreach (self::cases() as [$transcript, $type, $target]) {
            if ($type === 'navigate') {
                $this->assertTrue(app('router')->has($target), $target);
            }
        }
        $this->assertTrue(app('router')->has('scan.object'));
    }
}
