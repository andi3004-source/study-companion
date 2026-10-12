<?php

namespace App\Http\Controllers;

use App\Services\Scan\ScanService;
use App\Support\AiErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /scan-benda/kenali. The photo arrives as base64 inside the JSON body,
 * so it is only ever held in memory: no upload temp file, no storage, no
 * blob column, and neither the image nor model text is written to the log.
 */
class ScanBendaController extends Controller
{
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function recognize(Request $request, ScanService $scan): JsonResponse
    {
        abort_unless(config('scan.enabled'), 404);

        $request->validate(
            ['consent' => ['accepted'], 'image' => ['required', 'string']],
            [
                'consent.accepted' => 'Centang persetujuan dulu sebelum mengirim foto.',
                'image.required' => 'Foto belum ada.',
            ],
        );

        $maxBytes = (int) config('scan.max_image_bytes', 4000000);
        $encoded = preg_replace('/^data:[^,]*,/', '', (string) $request->input('image'));

        // Reject before decoding when the base64 text alone is already too big.
        if (strlen($encoded) > (int) ceil($maxBytes / 3) * 4 + 4) {
            return $this->invalid('Foto terlalu besar. Coba foto lagi.');
        }

        $bytes = base64_decode($encoded, true);

        if ($bytes === false || $bytes === '') {
            return $this->invalid('Foto tidak bisa dibaca. Coba foto lagi.');
        }

        if (strlen($bytes) > $maxBytes) {
            return $this->invalid('Foto terlalu besar. Coba foto lagi.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mime, self::MIMES, true)) {
            return $this->invalid('Format foto harus JPEG, PNG, atau WebP.');
        }

        try {
            $answer = $scan->recognize($bytes, $mime);
        } catch (Throwable $e) {
            // Class name only: messages can carry request URLs or model text.
            Log::warning('scan-benda: pengenalan gagal', ['error' => $e::class]);

            return response()->json(['jenis' => 'galat', 'pesan' => AiErrorMessage::for($e)], 503);
        } finally {
            unset($bytes, $encoded);
        }

        return response()->json($answer);
    }

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['jenis' => 'galat', 'pesan' => $message, 'errors' => ['image' => [$message]]], 422);
    }
}
