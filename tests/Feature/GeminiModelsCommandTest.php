<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiModelsCommandTest extends TestCase
{
    private const KEY = 'kunci-rahasia-uji';

    private const LIST_URL = 'generativelanguage.googleapis.com/v1beta/models*';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.gemini.key' => self::KEY]);
    }

    private function listModels(): array
    {
        $exit = Artisan::call('gemini:models');

        return [$exit, str_replace("\r\n", "\n", Artisan::output())];
    }

    public function test_prints_only_names_of_models_that_support_generate_content(): void
    {
        Http::fake([self::LIST_URL => Http::response(['models' => [
            ['name' => 'models/gemini-3.6-flash', 'displayName' => 'Tampilan Rahasia', 'description' => 'deskripsi-rahasia', 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
            ['name' => 'models/text-embedding-9', 'supportedGenerationMethods' => ['embedContent']],
            ['name' => 'models/gemini-3.6-flash-lite', 'supportedGenerationMethods' => ['generateContent']],
        ]])]);

        [$exit, $output] = $this->listModels();

        $this->assertSame(0, $exit);
        $this->assertSame(['gemini-3.6-flash', 'gemini-3.6-flash-lite'], array_values(array_filter(explode("\n", trim($output)))));
        $this->assertStringNotContainsString('embedding', $output);
        $this->assertStringNotContainsString('rahasia', strtolower($output));
    }

    public function test_sends_the_key_in_a_header_not_the_url(): void
    {
        Http::fake([self::LIST_URL => Http::response(['models' => []])]);

        $this->listModels();

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->header('x-goog-api-key') === [self::KEY]
            && ! str_contains($request->url(), self::KEY));
    }

    public function test_follows_next_page_tokens(): void
    {
        Http::fake([self::LIST_URL => Http::sequence()
            ->push(['models' => [['name' => 'models/b-model', 'supportedGenerationMethods' => ['generateContent']]], 'nextPageToken' => 'hal-2'])
            ->push(['models' => [['name' => 'models/a-model', 'supportedGenerationMethods' => ['generateContent']]]])]);

        [, $output] = $this->listModels();

        $this->assertSame("a-model\nb-model", trim($output));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'pageToken=hal-2'));
    }

    public function test_missing_key_gives_a_friendly_message_without_calling(): void
    {
        config(['services.gemini.key' => null]);

        [$exit, $output] = $this->listModels();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('GEMINI_API_KEY belum diisi', $output);
        Http::assertNothingSent();
    }

    public function test_http_errors_give_friendly_messages_without_body_or_key(): void
    {
        $status = null;
        Http::fake(function () use (&$status) {
            return Http::response(['error' => ['message' => 'API key '.self::KEY.' not valid, isi-badan-rahasia']], $status);
        });

        $expected = [
            400 => 'Kunci Gemini ditolak (HTTP 400)',
            403 => 'Kunci Gemini ditolak (HTTP 403)',
            429 => 'Kuota Gemini habis',
            503 => 'Layanan Gemini sedang bermasalah (HTTP 503)',
            404 => 'Daftar model gagal diambil (HTTP 404)',
        ];

        foreach ($expected as $status => $message) {
            [$exit, $output] = $this->listModels();

            $this->assertSame(1, $exit, "HTTP {$status}");
            $this->assertStringContainsString($message, $output);
            $this->assertStringNotContainsString(self::KEY, $output);
            $this->assertStringNotContainsString('isi-badan-rahasia', $output);
        }
    }

    public function test_connection_error_gives_a_friendly_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6 https://generativelanguage.googleapis.com/?key='.self::KEY));

        [$exit, $output] = $this->listModels();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Tidak bisa terhubung ke Gemini', $output);
        $this->assertStringNotContainsString(self::KEY, $output);
    }
}
