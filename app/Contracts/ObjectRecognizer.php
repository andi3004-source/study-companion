<?php

namespace App\Contracts;

/**
 * "Raba lalu dengar": picks one object from the closed catalog
 * (KataBicaraCatalog) for a photo. Implementations only ever return a
 * benda_id from that list; names always come from the catalog, never from
 * model text. The image bytes are processed in memory and never stored.
 */
interface ObjectRecognizer
{
    /**
     * @return array{
     *     status: 'ok'|'tidak_yakin'|'perlu_foto_ulang',
     *     benda_id: ?string,
     *     keyakinan: 'tinggi'|'sedang'|'rendah',
     *     bentuk: ?string,
     *     panduan_kamera: ?string,
     *     catatan: ?string,
     * }
     */
    public function recognize(string $imageBytes, string $mime): array;
}
