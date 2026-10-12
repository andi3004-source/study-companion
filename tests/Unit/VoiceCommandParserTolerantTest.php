<?php

namespace Tests\Unit;

use App\Support\VoiceCommandParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Natural speech: fillers, aliases, common mishearings, one-letter slips, "mulai". */
class VoiceCommandParserTolerantTest extends TestCase
{
    /** @return array<string, array{string, string, ?string, ?int}> transcript, type, target, index */
    public static function cases(): array
    {
        return [
            // Fillers, anywhere in the sentence.
            'masuk ke game' => ['masuk ke game', 'navigate', 'game', null],
            'pergi ke kamus' => ['Pergi ke kamus', 'navigate', 'kamus', null],
            'bukakan kartu' => ['bukakan kartu', 'navigate', 'cards.index', null],
            'tes obrolan' => ['tes obrolan', 'navigate', 'conversation', null],
            'tolong buka halaman ujian' => ['tolong buka halaman ujian', 'navigate', 'ujian', null],
            'aku mau ke kamus dong' => ['aku mau ke kamus dong', 'navigate', 'kamus', null],
            'yuk ke menu isian deh' => ['yuk ke menu isian deh', 'navigate', 'latihan-isian', null],
            'saya ingin fitur progress' => ['saya ingin fitur progress', 'navigate', 'progress', null],
            'coba ngobrol' => ['coba ngobrol', 'navigate', 'conversation', null],
            'ayo lanjut' => ['ayo lanjut', 'more', null, null],
            'ulangi dong' => ['ulangi dong', 'repeat', null, null],
            'tes alone' => ['tes', 'unknown', null, null],
            'tes ujian' => ['tes ujian', 'navigate', 'ujian', null],

            // Page aliases.
            'gim' => ['gim', 'navigate', 'game', null],
            'permainan' => ['buka permainan', 'navigate', 'game', null],
            'main game' => ['main game', 'navigate', 'game', null],
            'mau main game' => ['mau main game', 'navigate', 'game', null],
            'chat' => ['chat', 'navigate', 'conversation', null],
            'ngomong' => ['ngomong', 'navigate', 'conversation', null],
            'pengucapan' => ['pengucapan', 'navigate', 'pronunciation-check', null],
            'kamus bicara' => ['kamus bicara', 'navigate', 'kata-bicara', null],
            'flashcard' => ['flashcard', 'navigate', 'cards.index', null],
            'kuis' => ['Kuis', 'navigate', 'ujian', null],
            'bantu' => ['bantu', 'navigate', 'bantuan', null],
            'kemajuan' => ['masuk kemajuan', 'navigate', 'progress', null],

            // How id-ID recognition hears "game".
            'gem' => ['buka gem', 'navigate', 'game', null],
            'geim' => ['geim', 'navigate', 'game', null],
            'gaim' => ['masuk ke gaim', 'navigate', 'game', null],
            'gam' => ['gam', 'navigate', 'game', null],
            'main gem' => ['main gem', 'navigate', 'game', null],

            // Start.
            'mulai' => ['Mulai', 'start', null, null],
            'mulai main' => ['mulai main', 'start', null, null],
            'main' => ['main', 'start', null, null],
            'ayo mulai' => ['ayo mulai', 'start', null, null],
            'main lagi' => ['main lagi', 'start', null, null],
            'mulai game' => ['mulai game', 'start', null, null],
            'mulai pemburu kesalahan' => ['mulai pemburu kesalahan', 'start', 'game.error-hunter', null],
            'main dengar dan pilih' => ['main dengar dan pilih', 'start', 'game.listen-pick', null],
            'mulai isi stays dictation' => ['mulai isi', 'dictate_start', null, null],
            'mulai unknown thing' => ['mulai kamus', 'unknown', null, null],

            // Answer synonyms.
            'pilih satu' => ['pilih satu', 'choose_number', null, 0],
            'pilihan satu' => ['pilihan satu', 'choose_number', null, 0],
            'jawaban dua' => ['jawaban dua', 'choose_number', null, 1],
            'jawab tiga' => ['jawab tiga', 'choose_number', null, 2],
            'opsi empat' => ['opsi empat', 'choose_number', null, 3],
            'nomor tiga' => ['nomor tiga', 'choose_number', null, 2],
            'yang pertama' => ['yang pertama', 'choose_number', null, 0],
            'saya pilih dua dong' => ['saya pilih dua dong', 'choose_number', null, 1],

            // One misheard letter (5+ letters only).
            'kamuss' => ['kamuss', 'navigate', 'kamus', null],
            'progres' => ['progres', 'navigate', 'progress', null],
            'lanjud' => ['lanjud', 'more', null, null],
            'ulanggi' => ['ulanggi', 'repeat', null, null],
            'berhenty' => ['berhenty', 'stop', null, null],
            'mulay' => ['mulay', 'start', null, null],
            'pemburu kesalaham' => ['buka pemburu kesalaham', 'navigate', 'game.error-hunter', null],
            'two letters off' => ['kamuzz', 'unknown', null, null],
            'short word not fuzzy (kamu)' => ['kamu', 'unknown', null, null],
            'short word not fuzzy (isu)' => ['isu', 'unknown', null, null],

            // Still unknown: two commands, two numbers, clashing commands.
            'two commands' => ['kembali lanjut', 'unknown', null, null],
            'two pages with fillers' => ['buka kamus dan kartu', 'unknown', null, null],
            'two different numbers' => ['jawab dua tiga', 'unknown', null, null],
            'start plus number' => ['mulai satu', 'unknown', null, null],
            'page plus command' => ['kamus berhenti', 'unknown', null, null],
            'free sentence' => ['saya mau makan nasi goreng', 'unknown', null, null],
        ];
    }

    #[DataProvider('cases')]
    public function test_parse(string $transcript, string $type, ?string $target, ?int $index): void
    {
        config(['scan.enabled' => false]);

        $this->assertSame(['type' => $type, 'target' => $target, 'index' => $index], VoiceCommandParser::parse($transcript));
    }

    public function test_two_equally_close_candidates_ask_to_clarify(): void
    {
        // "usian" is one letter from both "ujian" and "isian".
        $result = VoiceCommandParser::parse('buka usian');

        $this->assertSame('clarify', $result['type']);
        $this->assertNull($result['target']);
        $this->assertSame(
            [
                ['type' => 'navigate', 'target' => 'ujian', 'index' => null, 'label' => 'ujian'],
                ['type' => 'navigate', 'target' => 'latihan-isian', 'index' => null, 'label' => 'isian'],
            ],
            $result['candidates'],
        );
    }

    public function test_scan_aliases_depend_on_the_flag(): void
    {
        foreach (['raba lalu dengar', 'kamera', 'potret', 'masuk ke kamera', 'tolong buka potret dong', 'kamerra'] as $phrase) {
            config(['scan.enabled' => false]);
            $this->assertSame(['type' => 'not_available', 'target' => 'scan.object', 'index' => null], VoiceCommandParser::parse($phrase), $phrase);

            config(['scan.enabled' => true]);
            $this->assertSame(['type' => 'navigate', 'target' => 'scan.object', 'index' => null], VoiceCommandParser::parse($phrase), $phrase);
        }
    }

    public function test_every_target_is_a_real_route(): void
    {
        config(['scan.enabled' => true]);

        foreach (self::cases() as [$transcript, $type, $target]) {
            if ($target !== null) {
                $this->assertTrue(app('router')->has($target), $target);
            }
        }
    }
}
