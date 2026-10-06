<?php

namespace App\Services;

use App\Contracts\SessionAnalyzer;
use App\Support\ErrorAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Error analysis through an IBM Langflow flow (POST /api/v1/run/{flow_id}),
 * whose prompt lives in Langflow itself. Langflow's run response nests the
 * chat output several levels deep and its exact shape depends on the flow's
 * components and version, so extractText() tries the known locations and
 * then searches. Any failure throws; FallbackSessionAnalyzer then falls back
 * to Gemini.
 */
class LangflowSessionAnalyzer implements SessionAnalyzer
{
    private string $url;

    private ?string $flowId;

    private ?string $apiKey;

    private int $timeout;

    public function __construct(?string $url = null, ?string $flowId = null, ?string $apiKey = null, ?int $timeout = null)
    {
        $this->url = rtrim((string) ($url ?? config('services.langflow.url')), '/');
        $this->flowId = $flowId ?? config('services.langflow.flow_id');
        $this->apiKey = $apiKey ?? config('services.langflow.api_key');
        $timeout ??= (int) config('services.langflow.timeout', 60);
        $this->timeout = $timeout > 0 ? $timeout : 60; // 0 would mean "no timeout" to Guzzle
    }

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->flowId);
    }

    public function analyze(string $transcript): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('LANGFLOW_URL / LANGFLOW_FLOW_ID are not set; cannot call Langflow.');
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->withHeaders(filled($this->apiKey) ? ['x-api-key' => $this->apiKey] : [])
            ->post("{$this->url}/api/v1/run/{$this->flowId}", [
                'input_value' => $transcript,
                'input_type' => 'chat',
                'output_type' => 'chat',
            ])
            ->throw();

        $text = self::extractText((array) $response->json());

        if ($text === null) {
            Log::debug('LangflowSessionAnalyzer: no text found in the run response.', ['body' => $response->body()]);

            throw new RuntimeException('Langflow response contains no output text.');
        }

        return ErrorAnalysis::parse($text);
    }

    public static function extractText(array $data): ?string
    {
        foreach (['outputs.0.outputs.0.results.message.text', 'outputs.0.outputs.0.outputs.message.message'] as $path) {
            $value = data_get($data, $path);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return self::findText($data);
    }

    /** First non-empty string under a 'text' or 'message' key, depth-first in document order. */
    private static function findText(array $node): ?string
    {
        foreach ($node as $key => $value) {
            if (in_array($key, ['text', 'message'], true) && is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_array($value) && ($found = self::findText($value)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
