<?php

namespace App\Contracts;

/**
 * Provider-agnostic contract for the Conversation Partner's LLM calls.
 * ConversationService depends on this, not on a concrete client, so the
 * active provider (Anthropic, Gemini, ...) is swapped purely via the
 * container binding in AppServiceProvider — no logic in ConversationService
 * (rate limit, history window, system prompt building, scripted vs random
 * mode) ever needs to know which provider is behind it.
 */
interface AiClient
{
    public function isConfigured(): bool;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  role is 'user'|'assistant'; each concrete client maps 'assistant' to whatever its own API calls it.
     * @return array{text: string, tokens_in: ?int, tokens_out: ?int}
     */
    public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array;
}
