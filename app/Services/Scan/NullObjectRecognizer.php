<?php

namespace App\Services\Scan;

use App\Contracts\ObjectRecognizer;

/** Used when no vision model is configured: never guesses. */
class NullObjectRecognizer implements ObjectRecognizer
{
    public const CATATAN = 'model visi belum terhubung';

    public function recognize(string $imageBytes, string $mime): array
    {
        return RecognitionNormalizer::unsure(self::CATATAN);
    }
}
