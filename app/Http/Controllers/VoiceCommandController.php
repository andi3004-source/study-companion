<?php

namespace App\Http\Controllers;

use App\Support\VoiceCommandParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /voice/command: parses one transcript and returns only
 * {type, target, index} (plus candidate commands for 'clarify'). The
 * transcript is not stored, logged or echoed back.
 */
class VoiceCommandController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['transcript' => ['present', 'nullable', 'string', 'max:200']]);

        return response()->json(VoiceCommandParser::parse((string) $request->input('transcript', '')));
    }
}
