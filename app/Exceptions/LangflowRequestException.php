<?php

namespace App\Exceptions;

use App\Support\SecretRedactor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A non-2xx Langflow run response. The message carries the HTTP status and
 * the first 500 characters of the (redacted) body, so the report() in
 * FallbackSessionAnalyzer / AnalyzeSessionJob shows why Langflow failed
 * without ever logging a key. Laravel's own RequestException is not used
 * because its message includes the raw body.
 *
 * transient: worth retrying later (rate limit, gateway errors, an
 * overloaded model). Everything else — 401/403/422, a quota error, a 500
 * from a broken flow — is permanent and goes straight to the fallback.
 */
class LangflowRequestException extends RuntimeException
{
    public const TRANSIENT_STATUSES = [429, 502, 503, 504];

    private const TRANSIENT_PHRASES = ['high demand', 'overloaded'];

    public function __construct(string $message, public readonly int $status, public readonly bool $transient)
    {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->body();

        return new self(
            "Langflow HTTP {$response->status()}: ".SecretRedactor::snippet($body),
            $response->status(),
            self::isTransient($response->status(), $body),
        );
    }

    public static function isTransient(int $status, string $body): bool
    {
        return in_array($status, self::TRANSIENT_STATUSES, true)
            || Str::contains($body, self::TRANSIENT_PHRASES, ignoreCase: true);
    }
}
