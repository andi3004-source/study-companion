<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Turns an AI-call failure into a friendly Indonesian message for the UI.
 * Display only: it never retries and never touches quota.
 */
class AiErrorMessage
{
    public const BUSY = 'Layanan AI sedang penuh, coba lagi sebentar.';

    public const TIMEOUT = 'Layanan AI terlalu lama menjawab. Coba kirim lagi.';

    public const GENERAL = 'AI sedang tidak tersedia. Coba lagi nanti.';

    public static function for(Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if ($e instanceof RequestException && in_array($e->response->status(), [429, 503], true)) {
            return self::BUSY;
        }

        if (str_contains($message, 'high demand') || str_contains($message, 'quota') || str_contains($message, 'resource_exhausted') || str_contains($message, 'rate limit')) {
            return self::BUSY;
        }

        if ($e instanceof ConnectionException || str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return self::TIMEOUT;
        }

        return self::GENERAL;
    }
}
