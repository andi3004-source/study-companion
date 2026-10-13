<?php

/**
 * Conversation Partner (Ngobrol) settings.
 *
 * daily_message_limit: how many messages one user may send to the AI per day.
 * Read through ConversationService::dailyMessageLimit(), which falls back to
 * ConversationService::DAILY_MESSAGE_LIMIT (50) unless the value is a whole
 * number between 1 and ConversationService::MAX_DAILY_MESSAGE_LIMIT.
 */
return [
    'daily_message_limit' => env('DAILY_MESSAGE_LIMIT', 50),
];
