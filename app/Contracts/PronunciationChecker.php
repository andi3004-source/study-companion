<?php

namespace App\Contracts;

/**
 * Provider-agnostic contract for checking a learner's spoken pronunciation
 * against a target word/phrase. Two implementations exist:
 * SttMatchPronunciationChecker (free, local, string-matching against a
 * browser STT transcript) and MlPronunciationChecker (calls the
 * pronunciation-service microservice for phoneme/tone-level analysis, with
 * SttMatchPronunciationChecker as its automatic fallback). Which one is
 * bound is controlled purely by PRONUNCIATION_CHECKER_DRIVER in
 * AppServiceProvider — callers never branch on the driver themselves.
 */
interface PronunciationChecker
{
    /**
     * @param  string  $audioPath  Absolute path to the recorded audio file (wav/webm). Only
     *                             consulted by implementations that do acoustic analysis; a
     *                             pure text-matching implementation may ignore it.
     * @param  string  $targetText  The word/phrase/character(s) the learner was asked to say.
     * @param  string  $lang  'english' or 'mandarin'.
     * @param  string|null  $sttTranscript  What the browser's speech recognizer heard, if
     *                                      available. Only consulted by implementations that
     *                                      do text matching; an acoustic implementation may
     *                                      ignore it.
     * @param  string|null  $targetPinyin  Target's pinyin, for display in feedback messages
     *                                     only (never used for matching logic). Mandarin only.
     * @return array{isCorrect: bool, feedback: string}
     */
    public function check(string $audioPath, string $targetText, string $lang, ?string $sttTranscript = null, ?string $targetPinyin = null): array;
}
