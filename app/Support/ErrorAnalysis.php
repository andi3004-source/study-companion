<?php

namespace App\Support;

use App\Enums\ErrorCategory;
use RuntimeException;

/**
 * Parses and normalizes an analyzer's answer into the stored
 * conversation_sessions.error_analysis shape:
 * {"items": [{"kategori", "contoh", "koreksi", "penjelasan"}], "catatan": ?string,
 * "sumber"?: "langflow"|"gemini"}. 'sumber' is set by the analyzer that actually
 * produced the result; sessions analyzed before it existed simply lack it.
 * Shared by every SessionAnalyzer so Langflow and Gemini output are held to
 * exactly the same rules. A successful analysis with no mistakes is
 * {"items": [], ...} — never NULL, which means "tried and failed".
 */
class ErrorAnalysis
{
    public const MAX_ITEMS = 5;

    /** Values of the 'sumber' key: which analyzer produced the result. */
    public const SOURCES = ['langflow', 'gemini'];

    /** @throws RuntimeException when $text isn't a JSON object/array (after stripping a ```json fence) */
    public static function parse(string $text): array
    {
        $text = trim($text);
        // Models (and Langflow chat outputs) often wrap JSON in a ```json fence despite being told not to.
        $text = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Session analysis is not valid JSON.');
        }

        // 'sumber' is the analyzer's to set, never the model's.
        unset($decoded['sumber']);

        return self::normalize($decoded);
    }

    /** Keeps only the known keys (so e.g. an analyzer's 'usage' never gets stored, and 'sumber' only when valid), maps categories, caps items. */
    public static function normalize(array $data): array
    {
        $items = array_is_list($data) ? $data : ($data['items'] ?? []);

        $normalized = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || ! is_string($item['contoh'] ?? null) || trim($item['contoh']) === '') {
                continue;
            }

            $normalized[] = [
                'kategori' => ErrorCategory::fromLoose($item['kategori'] ?? null)->value,
                'contoh' => trim($item['contoh']),
                'koreksi' => is_string($item['koreksi'] ?? null) ? trim($item['koreksi']) : '',
                'penjelasan' => is_string($item['penjelasan'] ?? null) ? trim($item['penjelasan']) : '',
            ];
        }

        $catatan = array_is_list($data) ? null : ($data['catatan'] ?? null);

        $sumber = array_is_list($data) ? null : ($data['sumber'] ?? null);

        return [
            'items' => array_slice($normalized, 0, self::MAX_ITEMS),
            'catatan' => is_string($catatan) && trim($catatan) !== '' ? trim($catatan) : null,
        ] + (in_array($sumber, self::SOURCES, true) ? ['sumber' => $sumber] : []);
    }
}
