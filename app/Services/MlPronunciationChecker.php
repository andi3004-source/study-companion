<?php

namespace App\Services;

use App\Contracts\PronunciationChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calls the pronunciation-service microservice (FastAPI, wraps OpenPronounce
 * for English and tone-me for Mandarin) for phoneme/tone-level analysis.
 *
 * That service is a research/community project, not Azure — it will time
 * out or be down sometimes. Any failure (connection refused, timeout,
 * non-2xx) is swallowed here and silently downgraded to
 * SttMatchPronunciationChecker so the user always gets a result, just a
 * less precise one. Each fallback is logged so how often the Python side
 * misbehaves stays visible.
 */
class MlPronunciationChecker implements PronunciationChecker
{
    private string $baseUrl;

    public function __construct(private readonly SttMatchPronunciationChecker $fallback, ?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.pronunciation_service.url'), '/');
    }

    public function check(string $audioPath, string $targetText, string $lang, ?string $sttTranscript = null, ?string $targetPinyin = null): array
    {
        // tone-me's model classifies a single syllable's tone. Splitting a
        // multi-character recording into per-character audio needs syllable
        // boundary timestamps this service never collects (tone-me's own
        // frontend gets those from the user tapping out syllable breaks —
        // see app.py's /predict_audio route), so running it on a
        // multi-character clip would silently score the wrong audio span
        // against the wrong tone. Go straight to the per-character text diff
        // instead, which is exact and honest about what it's comparing.
        if ($lang === 'mandarin' && mb_strlen($targetText) > 1) {
            return $this->fallback->check($audioPath, $targetText, $lang, $sttTranscript, $targetPinyin);
        }

        try {
            $endpoint = $lang === 'mandarin' ? '/check/mandarin' : '/check/english';

            $response = Http::timeout(5)
                ->attach('file', file_get_contents($audioPath) ?: '', basename($audioPath))
                ->post($this->baseUrl.$endpoint, ['target' => $targetText])
                ->throw();

            return $lang === 'mandarin'
                ? $this->fromMandarinResponse($response->json())
                : $this->fromEnglishResponse($response->json());
        } catch (Throwable $e) {
            Log::warning('pronunciation-service unreachable, falling back to STT-match', [
                'lang' => $lang,
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->check($audioPath, $targetText, $lang, $sttTranscript, $targetPinyin);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function fromEnglishResponse(array $data): array
    {
        $isCorrect = (bool) ($data['is_correct'] ?? false);
        $score = round((float) ($data['score'] ?? 0));
        $mispronounced = $data['mispronounced_words'] ?? [];

        $feedback = $isCorrect
            ? "Bagus! Skor pengucapan {$score}/100."
            : "Kurang tepat (skor {$score}/100). Kata yang perlu dilatih: ".(empty($mispronounced) ? '-' : implode(', ', $mispronounced)).'.';

        return ['isCorrect' => $isCorrect, 'feedback' => $feedback];
    }

    /** @param  array<string, mixed>  $data */
    private function fromMandarinResponse(array $data): array
    {
        $isCorrect = (bool) ($data['is_correct'] ?? false);
        $predicted = $data['predicted_tone'] ?? '?';
        $target = $data['target_tone'] ?? '?';

        $feedback = $isCorrect
            ? "Bagus! Nada {$target} sudah tepat."
            : "Nada kurang tepat. Target nada {$target}, yang terdengar nada {$predicted}.";

        return ['isCorrect' => $isCorrect, 'feedback' => $feedback];
    }
}
