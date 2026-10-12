<?php

namespace App\Services\Scan;

use App\Contracts\ObjectRecognizer;
use App\Services\KataBicaraCatalog;

/**
 * Builds the final "Raba lalu dengar" answer. Names (Indonesian, English,
 * hanzi, pinyin, IPA) always come from the catalog; from the model only the
 * chosen benda_id, its confidence, the shape description and a camera hint
 * are used. Only 'tinggi' confidence is announced as certain.
 */
class ScanService
{
    public const PESAN_TIDAK_YAKIN = 'Saya tidak yakin benda apa ini. Coba foto lagi dengan satu benda saja.';

    public const PESAN_MUNGKIN = 'Saya belum yakin. Mau foto lagi?';

    public const PESAN_FOTO_ULANG = 'Fotonya belum cukup jelas. Coba foto lagi.';

    public function __construct(
        private readonly ObjectRecognizer $recognizer,
        private readonly KataBicaraCatalog $catalog,
    ) {}

    public function recognize(string $imageBytes, string $mime): array
    {
        return $this->answer($this->recognizer->recognize($imageBytes, $mime));
    }

    public function answer(array $result): array
    {
        if (($result['status'] ?? null) === 'perlu_foto_ulang') {
            return [
                'jenis' => 'foto_ulang',
                'pesan' => self::PESAN_FOTO_ULANG,
                'panduan_kamera' => $result['panduan_kamera'] ?? null,
            ];
        }

        $entry = ($result['status'] ?? null) === 'ok' ? $this->catalog->find($result['benda_id'] ?? null) : null;

        if ($entry === null) {
            return [
                'jenis' => 'tidak_yakin',
                'pesan' => self::PESAN_TIDAK_YAKIN,
                'catatan' => $result['catatan'] ?? null,
            ];
        }

        $benda = [
            'indonesia' => $entry['indonesia'],
            'inggris' => $entry['inggris'],
            'ipa_inggris' => $entry['ipa_inggris'],
            'hanzi' => $entry['hanzi'],
            'pinyin' => $entry['pinyin'],
        ];

        if (($result['keyakinan'] ?? null) === 'tinggi') {
            return [
                'jenis' => 'pasti',
                'pesan' => 'Ini '.$entry['indonesia'].'.',
                'benda' => $benda,
                'bentuk' => $result['bentuk'] ?? null,
            ];
        }

        return [
            'jenis' => 'mungkin',
            'pesan' => 'Mungkin '.$entry['indonesia'].'. '.self::PESAN_MUNGKIN,
            'benda' => $benda,
            'bentuk' => $result['bentuk'] ?? null,
        ];
    }
}
