<?php

namespace Tests\Feature;

use App\Enums\ErrorCategory;
use App\Services\LangflowSessionAnalyzer;
use App\Support\ErrorAnalysis;
use App\Support\SecretRedactor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

/**
 * Checks LangflowSessionAnalyzer against a real Langflow run response.
 *
 * To refresh: run `php artisan langflow:ping --dump-structure`, check
 * storage/app/langflow-last-response.json, and copy it over FIXTURE. These
 * tests only assume the documented shape (text that parses as
 * {"items": [...], "catatan": ...}), never particular mistakes, so any real
 * analysis works. When the flow's output moves, the failure message names
 * the paths tried and the keys that were found.
 */
class LangflowFixtureTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/langflow_response.json';

    private const KNOWN_PATHS = ['outputs.0.outputs.0.results.message.text', 'outputs.0.outputs.0.outputs.message.message'];

    private function raw(): string
    {
        $path = base_path(self::FIXTURE);
        $this->assertFileExists($path, self::FIXTURE.' is missing. Create it from `php artisan langflow:ping --dump-structure`.');

        return file_get_contents($path);
    }

    private function fixture(): array
    {
        $data = json_decode($this->raw(), true);

        $this->assertIsArray($data, self::FIXTURE.' is not a JSON object: '.json_last_error_msg().'. Copy storage/app/langflow-last-response.json as-is.');

        return $data;
    }

    private function extractedText(): string
    {
        $data = $this->fixture();
        $text = LangflowSessionAnalyzer::extractText($data);

        $this->assertNotNull($text, sprintf(
            "No output text found in %s.\nKnown paths tried: %s, then any non-empty string under a 'text' or 'message' key.\n".
            "Top-level keys: %s; keys at outputs.0.outputs.0: %s.\n".
            'Find where the Chat Output text sits in the --dump-structure tree and add that path to LangflowSessionAnalyzer::extractText().',
            self::FIXTURE,
            implode(', ', self::KNOWN_PATHS),
            implode(', ', array_keys($data)) ?: '(none)',
            implode(', ', array_keys((array) data_get($data, 'outputs.0.outputs.0', []))) ?: '(none)',
        ));

        return $text;
    }

    public function test_fixture_contains_no_secrets(): void
    {
        $raw = $this->raw();

        $this->assertSame($raw, SecretRedactor::redact($raw), self::FIXTURE.' contains something that looks like an API key. Use the redacted file written by `langflow:ping --dump-structure`.');
    }

    /**
     * Independent of SecretRedactor: the raw key patterns, plus the value of
     * every key/secret/token in config('services'). Failure messages name
     * the pattern or config path, never the value.
     */
    public function test_fixture_matches_no_key_pattern_and_no_configured_key(): void
    {
        $raw = $this->raw();

        foreach (['sk-' => '/sk-[A-Za-z0-9_\-]+/', 'AIza' => '/AIza[0-9A-Za-z_\-]+/', 'AstraCS' => '/AstraCS:/'] as $name => $pattern) {
            $this->assertSame(0, preg_match($pattern, $raw), self::FIXTURE." contains a {$name}… key.");
        }

        $secrets = collect(Arr::dot(config('services')))
            ->filter(fn ($value, $path) => preg_match('/(^|\.|_)(key|secret|token)$/', $path) && is_string($value) && strlen(trim($value)) >= 8);

        foreach ($secrets as $path => $value) {
            $this->assertFalse(str_contains($raw, trim($value)), self::FIXTURE." contains the value of services.{$path}.");
        }
    }

    public function test_output_text_can_be_extracted_from_the_fixture(): void
    {
        $this->assertNotSame('', trim($this->extractedText()));
    }

    public function test_extracted_text_is_an_error_analysis(): void
    {
        $text = $this->extractedText();

        try {
            $analysis = ErrorAnalysis::parse($text);
        } catch (Throwable $e) {
            $this->fail(sprintf(
                "The text extracted from %s is not analysis JSON (%s). The extractor may have picked the wrong string, or the flow's prompt doesn't ask for JSON.\nExtracted text starts with: %s",
                self::FIXTURE, $e->getMessage(), mb_substr($text, 0, 200),
            ));
        }

        $categories = array_column(ErrorCategory::cases(), 'value');
        foreach ($analysis['items'] as $item) {
            $this->assertContains($item['kategori'], $categories);
            $this->assertNotSame('', $item['contoh']);
        }
        $this->assertLessThanOrEqual(ErrorAnalysis::MAX_ITEMS, count($analysis['items']));
    }

    public function test_the_analyzer_handles_the_fixture_end_to_end(): void
    {
        config(['services.langflow' => ['url' => 'https://langflow.test', 'flow_id' => 'flow-123', 'api_key' => null, 'timeout' => 120]]);
        Http::fake(['https://langflow.test/api/v1/run/flow-123?stream=false' => Http::response($this->raw(), 200, ['Content-Type' => 'application/json'])]);

        $result = app(LangflowSessionAnalyzer::class)->analyze('User: 我是高兴。');

        $this->assertSame(['items', 'catatan', 'sumber'], array_keys($result));
        $this->assertSame('langflow', $result['sumber']);
        $this->assertIsList($result['items']);
    }
}
