<?php

namespace Tests\Unit;

use App\Contracts\AiClient;
use App\Services\GeminiMeaningTranslator;
use RuntimeException;
use Tests\TestCase;

class GeminiMeaningTranslatorTest extends TestCase
{
    public function test_translates_a_batch_in_one_call_preserving_order(): void
    {
        $client = new class implements AiClient
        {
            public array $lastMessages = [];

            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                $this->lastMessages = $messages;

                return ['text' => json_encode(['kucing', 'anjing']), 'tokens_in' => 10, 'tokens_out' => 5];
            }
        };

        $translator = new GeminiMeaningTranslator($client);

        $result = $translator->translateBatch(['cat', 'dog']);

        $this->assertSame(['kucing', 'anjing'], $result);
        // One API call for the whole batch, not one per word.
        $this->assertSame(json_encode(['cat', 'dog']), $client->lastMessages[0]['content']);
    }

    public function test_strips_a_markdown_code_fence_gemini_sometimes_adds_anyway(): void
    {
        $client = new class implements AiClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                return ['text' => "```json\n[\"kucing\"]\n```", 'tokens_in' => null, 'tokens_out' => null];
            }
        };

        $result = (new GeminiMeaningTranslator($client))->translateBatch(['cat']);

        $this->assertSame(['kucing'], $result);
    }

    public function test_throws_when_gemini_returns_the_wrong_number_of_translations(): void
    {
        $client = new class implements AiClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                return ['text' => json_encode(['kucing']), 'tokens_in' => null, 'tokens_out' => null];
            }
        };

        $this->expectException(RuntimeException::class);

        (new GeminiMeaningTranslator($client))->translateBatch(['cat', 'dog']);
    }

    public function test_empty_batch_never_calls_the_client(): void
    {
        $client = new class implements AiClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                throw new RuntimeException('should not be called for an empty batch');
            }
        };

        $this->assertSame([], (new GeminiMeaningTranslator($client))->translateBatch([]));
    }
}
