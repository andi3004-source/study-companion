<?php

namespace App\Http\Controllers;

use App\Support\VoiceAlternatives;
use App\Support\VoiceCommandParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /voice/command: parses one transcript and returns only
 * {type, target, index} (plus candidate commands for 'clarify'). With
 * 'alternatives' (all recognition alternatives, best first) it also returns
 * a 'decision' (see VoiceAlternatives). Transcripts are not stored, logged
 * or echoed back.
 */
class VoiceCommandController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'transcript' => ['present', 'nullable', 'string', 'max:200'],
            'alternatives' => ['sometimes', 'array', 'min:1', 'max:'.VoiceAlternatives::MAX],
            'alternatives.*' => ['nullable', 'string', 'max:200'],
        ]);

        if ($request->has('alternatives')) {
            return response()->json(VoiceAlternatives::decide(array_map('strval', $request->input('alternatives'))));
        }

        return response()->json(VoiceCommandParser::parse((string) $request->input('transcript', '')));
    }
}
