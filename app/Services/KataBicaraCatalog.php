<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Local word catalog for the "Kata bicara" page: Indonesian / English / Mandarin
 * for one word. Reads the JSON file at config('katabicara.path'); no AI, no
 * network. Search is exact (case-insensitive) on the Indonesian name, English
 * name, or hanzi — a word that isn't in the list is reported as missing, never
 * guessed.
 */
class KataBicaraCatalog
{
    private ?Collection $entries = null;

    private bool $loaded = false;

    /** Usable entries (ikut_prompt = true), or null when the file is missing or invalid. */
    public function entries(): ?Collection
    {
        if ($this->loaded) {
            return $this->entries;
        }
        $this->loaded = true;

        $path = config('katabicara.path');
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
            $data = $data['items'];
        }
        if (! is_array($data) || ! array_is_list($data)) {
            return null;
        }

        return $this->entries = collect($data)
            ->filter(fn ($entry) => is_array($entry) && ($entry['ikut_prompt'] ?? false) === true)
            ->map(fn (array $entry) => [
                'indonesia' => trim((string) ($entry['indonesia'] ?? '')),
                'inggris' => trim((string) ($entry['inggris'] ?? '')),
                'ipa_inggris' => trim((string) ($entry['ipa_inggris'] ?? '')),
                'hanzi' => trim((string) ($entry['hanzi'] ?? '')),
                'pinyin' => trim((string) ($entry['pinyin'] ?? '')),
            ])
            ->filter(fn (array $entry) => $entry['indonesia'] !== '')
            ->values();
    }

    public function available(): bool
    {
        return $this->entries() !== null;
    }

    /** Entries whose Indonesian name, English name, or hanzi equals $query (case-insensitive). */
    public function search(string $query): Collection
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '' || ! $this->available()) {
            return collect();
        }

        return $this->entries()->filter(fn (array $entry) => in_array($needle, [
            mb_strtolower($entry['indonesia']),
            mb_strtolower($entry['inggris']),
            $entry['hanzi'],
        ], true))->values();
    }

    /** A few Indonesian names to suggest when nothing matched. */
    public function examples(int $count): array
    {
        return $this->available()
            ? $this->entries()->pluck('indonesia')->take($count)->all()
            : [];
    }
}
