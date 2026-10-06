<?php

namespace App\Console\Commands;

use App\Services\ConversationService;
use App\Support\HskVocabularyPrompt;
use Illuminate\Console\Command;

/**
 * Size of what the HSK1 level lock adds to every Mandarin system prompt (see
 * ConversationService::levelLockInstruction). Makes no API call: the token
 * count is a rough estimate — ~1 token per CJK character and ~1 token per 4
 * other characters. The real per-call input tokens are logged in
 * conversation_api_calls.tokens_in.
 */
class HskPromptStats extends Command
{
    protected $signature = 'hsk:prompt-stats';

    protected $description = 'Print the size and estimated token cost of the HSK1 level-lock prompt block';

    public function handle(ConversationService $service): int
    {
        $list = HskVocabularyPrompt::hsk1List();

        if ($list === '') {
            $this->warn('No HSK1 words in mandarin_words: the level lock adds nothing.');

            return self::SUCCESS;
        }

        $block = $service->levelLockInstruction();

        $this->line(sprintf('HSK1 list: %d words, %d characters, ~%d tokens.', count(explode(' ', $list)), mb_strlen($list), self::estimateTokens($list)));
        $this->line(sprintf('Whole lock block (instruction + list): %d characters, ~%d tokens.', mb_strlen($block), self::estimateTokens($block)));

        $this->info('Added to every Mandarin message while HSK_LEVEL_LOCK is on (estimate, not a tokenizer count).');

        return self::SUCCESS;
    }

    public static function estimateTokens(string $text): int
    {
        $cjk = preg_match_all('/\p{Han}/u', $text);
        $other = mb_strlen($text) - $cjk;

        return $cjk + (int) ceil($other / 4);
    }
}
