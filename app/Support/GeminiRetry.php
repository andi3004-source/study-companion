<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * The one place that retries Gemini HTTP calls (GeminiClient, so chat and the
 * Gemini error analyzer, and GeminiObjectRecognizer). Only a 503 or a
 * connection error/timeout is retried; 400, 403, 404 and 429 fail at once.
 * Everything, waits included, stays inside gemini.retry_budget_seconds.
 *
 * The caller's closure holds the request body (for a scan, the photo), so a
 * retry resends it from memory: nothing is written to disk here. The log gets
 * the error class, HTTP status, attempt number and model, never a message,
 * body, image or key.
 */
class GeminiRetry
{
    /** An attempt is not started with less time than this left in the budget. */
    private const MIN_ATTEMPT_SECONDS = 1.0;

    /**
     * @param  Closure(string $model, float $timeout): Response  $send  must throw on failure (->throw())
     */
    public static function send(string $model, float $timeout, Closure $send): Response
    {
        $budget = (float) config('gemini.retry_budget_seconds', 12);
        $maxRetries = max(0, (int) config('gemini.retry_max', 2));
        $start = Carbon::now();
        $attempt = 0;

        while (true) {
            $attempt++;
            $remaining = $budget - self::elapsed($start);

            try {
                return $send($model, min($timeout, max($remaining, self::MIN_ATTEMPT_SECONDS)));
            } catch (RequestException $e) {
                $status = $e->response->status();
                $retryable = $status === 503;
            } catch (ConnectionException $e) {
                $status = null;
                $retryable = true;
            }

            Log::warning('gemini: percobaan gagal', [
                'error' => $e::class,
                'status' => $status,
                'percobaan' => $attempt,
                'model' => $model,
            ]);

            if (! $retryable || $attempt > $maxRetries) {
                throw $e;
            }

            $delay = self::delay($attempt);

            if (self::elapsed($start) + $delay + self::MIN_ATTEMPT_SECONDS > $budget) {
                throw $e;
            }

            Sleep::for((int) round($delay * 1000))->milliseconds();
        }
    }

    /** Seconds to wait before retry number $retry (1-based), jitter included. */
    private static function delay(int $retry): float
    {
        $delays = config('gemini.retry_delays_seconds') ?: [1, 3];
        $base = (float) ($delays[$retry - 1] ?? end($delays));
        $jitterMs = max(0, (int) config('gemini.retry_jitter_ms', 250));

        return $base + random_int(0, $jitterMs) / 1000;
    }

    private static function elapsed(Carbon $start): float
    {
        return abs($start->diffInMilliseconds(Carbon::now())) / 1000;
    }
}
