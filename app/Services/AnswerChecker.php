<?php

namespace App\Services;

/**
 * Shared answer-matching rules for Latihan Isian (fill-in-the-blank practice).
 * Pinyin is tone-insensitive (diacritics stripped, spacing ignored) since
 * recalling tones correctly is a much harder skill than recalling the syllables;
 * Arti accepts any one of several "/"- or ","-separated synonyms because
 * Indonesian glosses commonly have more than one natural translation.
 */
class AnswerChecker
{
    private const DIACRITIC_MAP = [
        'ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a',
        'Ā' => 'a', 'Á' => 'a', 'Ǎ' => 'a', 'À' => 'a',
        'ē' => 'e', 'é' => 'e', 'ě' => 'e', 'è' => 'e',
        'Ē' => 'e', 'É' => 'e', 'Ě' => 'e', 'È' => 'e',
        'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
        'Ī' => 'i', 'Í' => 'i', 'Ǐ' => 'i', 'Ì' => 'i',
        'ō' => 'o', 'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o',
        'Ō' => 'o', 'Ó' => 'o', 'Ǒ' => 'o', 'Ò' => 'o',
        'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u',
        'Ū' => 'u', 'Ú' => 'u', 'Ǔ' => 'u', 'Ù' => 'u',
        'ǖ' => 'u', 'ǘ' => 'u', 'ǚ' => 'u', 'ǜ' => 'u',
        'Ǖ' => 'u', 'Ǘ' => 'u', 'Ǚ' => 'u', 'Ǜ' => 'u',
        'ü' => 'u', 'Ü' => 'u',
    ];

    public function normalizePinyin(string $value): string
    {
        $stripped = strtr($value, self::DIACRITIC_MAP);

        return preg_replace('/\s+/', '', strtolower(trim($stripped)));
    }

    public function hanziMatches(string $user, string $correct): bool
    {
        return trim($user) === trim($correct);
    }

    public function pinyinMatches(string $user, string $correct): bool
    {
        return $this->normalizePinyin($user) === $this->normalizePinyin($correct);
    }

    public function meaningMatches(string $user, string $correct): bool
    {
        $user = strtolower(trim($user));

        if ($user === '') {
            return false;
        }

        foreach (preg_split('/[\/,]/', $correct) as $variant) {
            $variant = strtolower(trim($variant));

            if ($variant === '') {
                continue;
            }

            if (str_contains($variant, $user) || str_contains($user, $variant)) {
                return true;
            }
        }

        return false;
    }
}
