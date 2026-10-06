<?php

namespace Tests\Unit;

use App\Contracts\AiClient;
use App\Services\GeminiMeaningTranslator;
use InvalidArgumentException;
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

    public function test_headwords_are_sent_as_hanzi_pinyin_en_objects(): void
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

                return ['text' => json_encode(['semua; juga']), 'tokens_in' => 10, 'tokens_out' => 5];
            }
        };

        $result = (new GeminiMeaningTranslator($client))
            ->translateBatch(['surname Du'], [['hanzi' => '都', 'pinyin' => 'dōu']]);

        $this->assertSame(['semua; juga'], $result);
        $this->assertSame(
            json_encode([['hanzi' => '都', 'pinyin' => 'dōu', 'en' => 'surname Du']], JSON_UNESCAPED_UNICODE),
            $client->lastMessages[0]['content']
        );
    }

    public function test_headwords_must_match_the_number_of_texts(): void
    {
        $client = new class implements AiClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                throw new RuntimeException('should not be called with mismatched headwords');
            }
        };

        $this->expectException(InvalidArgumentException::class);

        (new GeminiMeaningTranslator($client))->translateBatch(['cat', 'dog'], [['hanzi' => '猫', 'pinyin' => 'māo']]);
    }
}
