<?php

namespace App\Support;

/**
 * Reads the hand-checked HSK2-3 corrections file (config('hsk.corrections_path'),
 * a JSON array of {hanzi, pinyin, indonesian, hsk_level, pinyin_asli, ...}).
 * Each entry fixes one mandarin_hsk_words row, identified by its hanzi plus
 * the wrong pinyin it was imported with (pinyin_asli). HSK1 entries are
 * ignored: the HSK1 curriculum lives in mandarin_words and is repaired from
 * curated data instead (see `php artisan hsk:repair-curriculum`).
 */
class HskCorrections
{
    public const STATUS = 'corrected_draft';

    public static function path(): string
    {
        return (string) config('hsk.corrections_path');
    }

    public static function exists(): bool
    {
        return self::path() !== '' && is_file(self::path());
    }

    /**
     * Keyed by self::key(hanzi, pinyin_asli); empty when the file doesn't exist.
     *
     * @return array<string, array{hanzi: string, pinyin: string, indonesian: string, pinyin_asli: string}>
     */
    public static function byOriginalKey(): array
    {
        if (! self::exists()) {
            return [];
        }

        $entries = json_decode(file_get_contents(self::path()), true, 512, JSON_THROW_ON_ERROR);

        $corrections = [];
        foreach ($entries as $entry) {
            if ((int) $entry['hsk_level'] === 1) {
                continue;
            }

            $corrections[self::key($entry['hanzi'], $entry['pinyin_asli'])] = $entry;
        }

        return $corrections;
    }

    public static function key(string $hanzi, string $pinyin): string
    {
        return $hanzi."\u{0}".$pinyin;
    }
}
