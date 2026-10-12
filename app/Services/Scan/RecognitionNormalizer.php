<?php

namespace App\Services\Scan;

use App\Services\KataBicaraCatalog;

/**
 * Turns a vision model's raw reply into the ObjectRecognizer result shape.
 * Anything malformed, or a benda_id outside the closed catalog, becomes
 * tidak_yakin. Only benda_id, keyakinan, bentuk and panduan_kamera are kept
 * from the model; any name text it returns is dropped.
 */
class RecognitionNormalizer
{
    private const STATUSES = ['ok', 'tidak_yakin', 'perlu_foto_ulang'];

    private const KEYAKINAN = ['tinggi', 'sedang', 'rendah'];

    public function __construct(private readonly KataBicaraCatalog $catalog) {}

    public function normalize(string $raw): array
    {
        $data = json_decode($this->stripFences($raw), true);

        if (! is_array($data) || ! in_array($data['status'] ?? null, self::STATUSES, true)) {
            return self::unsure();
        }

        $keyakinan = in_array($data['keyakinan'] ?? null, self::KEYAKINAN, true) ? $data['keyakinan'] : 'rendah';

        if ($data['status'] === 'perlu_foto_ulang') {
            return [
                'status' => 'perlu_foto_ulang',
                'benda_id' => null,
                'keyakinan' => $keyakinan,
                'bentuk' => null,
                'panduan_kamera' => $this->shortText($data['panduan_kamera'] ?? null, 200),
                'catatan' => null,
            ];
        }

        $bendaId = is_scalar($data['benda_id'] ?? null) ? (string) $data['benda_id'] : null;

        if ($data['status'] !== 'ok' || $this->catalog->find($bendaId) === null) {
            return self::unsure();
        }

        return [
            'status' => 'ok',
            'benda_id' => $bendaId,
            'keyakinan' => $keyakinan,
            'bentuk' => $this->shortText($data['bentuk'] ?? null, 300),
            'panduan_kamera' => null,
            'catatan' => null,
        ];
    }

    public static function unsure(?string $catatan = null): array
    {
        return [
            'status' => 'tidak_yakin',
            'benda_id' => null,
            'keyakinan' => 'rendah',
            'bentuk' => null,
            'panduan_kamera' => null,
            'catatan' => $catatan,
        ];
    }

    /** Removes a ```json ... ``` (or plain ```) wrapper around the reply. */
    private function stripFences(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }

    private function shortText(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
