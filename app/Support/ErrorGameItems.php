<?php

namespace App\Support;

use App\Enums\ErrorCategory;

/**
 * The "Pemburu Kesalahan" question bank, read straight from the JSON file at
 * config('game.items_path') — no table, the file is small and edited by
 * hand. Memoized per path for the rest of the process, so a request only
 * reads it once (and a test pointing the config at a fixture gets that
 * fixture, not a stale copy of the real file).
 *
 * Entries without an id, a wrong sentence or its correction are skipped;
 * an unknown kategori becomes 'lainnya' (see ErrorCategory::fromLoose).
 */
class ErrorGameItems
{
    public const DRAFT_STATUS = 'draf_perlu_dicek_penutur';

    /** @var array<string, array<int, array>> path => items keyed by id */
    private static array $memo = [];

    public static function path(): string
    {
        return (string) config('game.items_path');
    }

    public static function exists(): bool
    {
        return self::path() !== '' && is_file(self::path());
    }

    /**
     * @return array<int, array{id: int, kategori: string, salah: string, benar: string, arti_id: string, penjelasan: string, hanzi_di_luar_hsk1: string[], status: ?string}>
     */
    public static function all(): array
    {
        return self::$memo[self::path()] ??= self::load();
    }

    public static function find(int|string $id): ?array
    {
        return self::all()[(int) $id] ?? null;
    }

    public static function isDraft(array $item): bool
    {
        return $item['status'] === self::DRAFT_STATUS;
    }

    /**
     * Categories that actually have questions — the game's only pool of
     * distractors, so a category with no text questions (nada, pelafalan)
     * never shows up as an obviously-wrong option.
     *
     * @return string[]
     */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(self::all(), 'kategori')));
    }

    public static function flush(): void
    {
        self::$memo = [];
    }

    private static function load(): array
    {
        if (! self::exists()) {
            return [];
        }

        $entries = json_decode(file_get_contents(self::path()), true, 512, JSON_THROW_ON_ERROR);

        $items = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (! is_array($entry) || ! is_numeric($entry['id'] ?? null)
                || blank($entry['salah'] ?? null) || blank($entry['benar'] ?? null)) {
                continue;
            }

            $id = (int) $entry['id'];
            $outside = $entry['hanzi_di_luar_hsk1'] ?? [];

            $items[$id] ??= [
                'id' => $id,
                'kategori' => ErrorCategory::fromLoose($entry['kategori'] ?? null)->value,
                'salah' => trim((string) $entry['salah']),
                'benar' => trim((string) $entry['benar']),
                'arti_id' => trim((string) ($entry['arti_id'] ?? '')),
                'penjelasan' => trim((string) ($entry['penjelasan'] ?? '')),
                'hanzi_di_luar_hsk1' => is_array($outside) ? array_values(array_filter($outside, 'is_string')) : [],
                'status' => is_string($entry['status'] ?? null) ? $entry['status'] : null,
            ];
        }

        return $items;
    }
}
