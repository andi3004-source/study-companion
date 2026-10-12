<?php

namespace App\Support;

/**
 * Turns one spoken Indonesian command (a speech-to-text transcript) into a
 * small, fixed command for "Mode suara". Pure and stateless; the transcript
 * is never stored or logged.
 *
 * Matching is on the whole utterance after normalising (lowercase, no
 * punctuation, filler words like "buka" or "tolong" removed), so a sentence
 * that mixes two commands, or two different numbers, is 'unknown' rather
 * than a guess.
 *
 * Result: ['type' => string, 'target' => ?string, 'index' => ?int]
 *   navigate        target = route name
 *   not_available   target = route name or null (feature off or not built)
 *   choose_number   index  = 0-based (1-12, or a-d)
 *   back, repeat, read_page, list_actions, more, confirm_yes, confirm_no,
 *   stop, help, dictate_start, dictate_stop, unknown
 */
class VoiceCommandParser
{
    public const MAX_NUMBER = 12;

    /** Words that may precede a page name or a command without changing it. */
    private const FILLERS = ['tolong', 'coba', 'buka', 'pergi', 'ke', 'halaman', 'menu', 'saya', 'mau', 'ingin'];

    /** phrase => route name */
    private const PAGES = [
        'hari ini' => 'dashboard',
        'dashboard' => 'dashboard',
        'kartu' => 'cards.index',
        'kamus' => 'kamus',
        'ujian' => 'ujian',
        'isian' => 'latihan-isian',
        'game' => 'game',
        'pemburu kesalahan' => 'game.error-hunter',
        'dengar dan pilih' => 'game.listen-pick',
        'ngobrol' => 'conversation',
        'obrolan' => 'conversation',
        'percakapan' => 'conversation',
        'ucapan' => 'pronunciation-check',
        'cek pengucapan' => 'pronunciation-check',
        'kata bicara' => 'kata-bicara',
        'raba lalu dengar' => 'scan.object',
        'kamera' => 'scan.object',
        'potret' => 'scan.object',
        'progress' => 'progress',
        'kemajuan' => 'progress',
        'bantuan' => 'bantuan',
    ];

    /** Pages that are mentioned in the command list but don't exist (yet). */
    private const NOT_BUILT = ['pilih yang benar'];

    /** type => phrases (whole utterance) */
    private const COMMANDS = [
        'back' => ['kembali', 'mundur', 'halaman sebelumnya'],
        'repeat' => ['ulangi', 'ulang', 'sekali lagi'],
        'read_page' => ['baca halaman', 'bacakan halaman', 'baca', 'bacakan'],
        'list_actions' => ['daftar tindakan', 'tindakan', 'apa saja', 'pilihan apa saja'],
        'more' => ['lanjut', 'lanjutkan'],
        'confirm_yes' => ['ya', 'iya', 'betul', 'benar', 'oke', 'ok', 'yes'],
        'confirm_no' => ['tidak', 'bukan', 'batal', 'jangan', 'no'],
        'stop' => ['berhenti', 'stop', 'diam', 'matikan mode suara'],
        'help' => ['perintah', 'bantuan suara', 'daftar perintah', 'apa yang bisa saya katakan'],
        'dictate_start' => ['isi', 'ketik', 'mulai isi', 'mulai ketik'],
        'dictate_stop' => ['selesai isi', 'berhenti isi', 'selesai ketik', 'berhenti ketik'],
    ];

    private const CARDINALS = [
        'dua belas' => 12, 'sebelas' => 11, 'sepuluh' => 10, 'sembilan' => 9, 'delapan' => 8, 'tujuh' => 7,
        'enam' => 6, 'lima' => 5, 'empat' => 4, 'tiga' => 3, 'dua' => 2, 'satu' => 1,
    ];

    /** Words allowed around a number in a choice ("nomor tiga", "pilihan dua", "yang kedua"). */
    private const NUMBER_WORDS = ['nomor', 'pilihan', 'pilih', 'yang', 'jawaban', 'opsi', 'huruf', 'angka'];

    public static function parse(string $transcript): array
    {
        $text = self::normalize($transcript);

        if ($text === '') {
            return self::result('unknown');
        }

        $matches = array_values(array_filter([
            self::page($text),
            self::command($text),
            self::number($text),
        ]));

        // Exactly one interpretation, or nothing: never guess between two.
        return count($matches) === 1 ? $matches[0] : self::result('unknown');
    }

    public static function normalize(string $transcript): string
    {
        $text = mb_strtolower($transcript);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function page(string $text): ?array
    {
        $phrase = self::withoutFillers($text);

        if (in_array($phrase, self::NOT_BUILT, true)) {
            return self::result('not_available');
        }

        $route = self::PAGES[$phrase] ?? null;

        if ($route === null) {
            return null;
        }

        if ($route === 'scan.object' && ! config('scan.enabled')) {
            return self::result('not_available', $route);
        }

        return self::result('navigate', $route);
    }

    private static function command(string $text): ?array
    {
        $found = [];
        foreach (self::COMMANDS as $type => $phrases) {
            if (in_array($text, $phrases, true) || in_array(self::withoutFillers($text), $phrases, true)) {
                $found[] = $type;
            }
        }

        return count($found) === 1 ? self::result($found[0]) : null;
    }

    private static function number(string $text): ?array
    {
        $words = explode(' ', $text);

        // A single letter a-d, optionally after "pilihan"/"jawaban"/"huruf".
        $letters = array_values(array_diff($words, self::NUMBER_WORDS));
        if (count($letters) === 1 && preg_match('/^[a-d]$/', $letters[0]) && count($words) <= 2) {
            return self::result('choose_number', null, ord($letters[0]) - ord('a'));
        }

        // Ordinals first ("kedua belas", "ketiga"), then cardinals, then "pertama".
        $cardinals = implode('|', array_keys(self::CARDINALS));
        $text = preg_replace_callback('/\bke ?('.$cardinals.')\b/u', fn ($m) => ' '.self::CARDINALS[$m[1]].' ', $text);
        $text = preg_replace_callback('/\b('.$cardinals.')\b/u', fn ($m) => ' '.self::CARDINALS[$m[1]].' ', $text);
        $text = preg_replace('/\b(pertama|kesatu)\b/u', ' 1 ', $text);

        $tokens = array_values(array_filter(explode(' ', $text), fn ($t) => $t !== ''));
        $numbers = array_values(array_unique(array_filter($tokens, fn ($t) => ctype_digit($t))));
        $rest = array_diff($tokens, $numbers, self::NUMBER_WORDS, ['ke']);

        if (count($numbers) !== 1 || $rest !== []) {
            return null;
        }

        $n = (int) $numbers[0];

        return $n >= 1 && $n <= self::MAX_NUMBER ? self::result('choose_number', null, $n - 1) : null;
    }

    private static function withoutFillers(string $text): string
    {
        $words = explode(' ', $text);
        while ($words !== [] && in_array($words[0], self::FILLERS, true) && count($words) > 1) {
            array_shift($words);
        }

        return implode(' ', $words);
    }

    private static function result(string $type, ?string $target = null, ?int $index = null): array
    {
        return ['type' => $type, 'target' => $target, 'index' => $index];
    }
}
