<?php

namespace App\Support;

use App\Models\ConversationSession;
use App\Models\ConversationTurn;

/**
 * The transcript a SessionAnalyzer reads. Voice turns are marked
 * "User (suara):" because they're STT transcripts: an odd hanzi there may
 * be a mishearing, not a grammar mistake, and the analyzer prompt treats
 * them accordingly. Assistant turns use content only — the Indonesian
 * translation of a Mandarin reply is stored separately (translation).
 */
class SessionTranscript
{
    public static function build(ConversationSession $session): string
    {
        return $session->turns()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ConversationTurn $t) => match (true) {
                $t->role === 'assistant' => 'AI: ',
                $t->input_mode === 'voice' => 'User (suara): ',
                default => 'User: ',
            }.$t->content)
            ->implode("\n");
    }
}
