<?php

namespace App\Support;

/**
 * Which meaning Kamus and Kartu show as the main one. meaning_id (Indonesian)
 * always wins; the English gloss only stands in for it when it's
 * trustworthy. The HSK dataset's glosses sometimes describe the wrong
 * homograph — a surname (都 "surname Du") or a slang sense — and showing
 * that as "the meaning" teaches the learner something wrong, so such a
 * gloss is only ever shown as a secondary detail. Only the FIRST sense
 * (before the first ";") decides: "airport; airfield; (slang) ..." (机场)
 * is a normal word whose dictionary entry merely lists a slang sense later.
 */
class MeaningDisplay
{
    public static function isUnreliableGloss(?string $meaningEn): bool
    {
        if ($meaningEn === null) {
            return false;
        }

        $firstSense = strtolower(trim(explode(';', $meaningEn, 2)[0]));

        return str_starts_with($firstSense, 'surname') || str_starts_with($firstSense, '(slang)');
    }

    /** @return ?string null = "Arti belum tersedia" */
    public static function primary(?string $meaningId, ?string $meaningEn): ?string
    {
        if (filled($meaningId)) {
            return $meaningId;
        }

        return filled($meaningEn) && ! self::isUnreliableGloss($meaningEn) ? $meaningEn : null;
    }

    /** True when the primary meaning is already the English gloss, so it shouldn't be repeated as the secondary detail. */
    public static function primaryIsEnglish(?string $meaningId, ?string $meaningEn): bool
    {
        return blank($meaningId) && self::primary($meaningId, $meaningEn) !== null;
    }
}
