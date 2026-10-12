<?php

namespace App\Providers;

use App\Contracts\AiClient;
use App\Contracts\ObjectRecognizer;
use App\Contracts\PronunciationChecker;
use App\Contracts\SessionAnalyzer;
use App\Services\FallbackSessionAnalyzer;
use App\Services\GeminiClient;
use App\Services\GeminiSessionAnalyzer;
use App\Services\LangflowSessionAnalyzer;
use App\Services\MlPronunciationChecker;
use App\Services\Scan\GeminiObjectRecognizer;
use App\Services\Scan\NullObjectRecognizer;
use App\Services\SttMatchPronunciationChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Active Conversation Partner LLM provider + its model name, set together
        // so ConversationService (config('services.active_ai_model')) never has to
        // hardcode a provider-specific config key. Swap both lines to
        // AnthropicClient::class / 'services.anthropic.model' to go back to Claude
        // — nothing in ConversationService needs to change.
        $this->app->bind(AiClient::class, GeminiClient::class);
        config(['services.active_ai_model' => config('services.gemini.model')]);

        // End-of-session error analysis: the Langflow flow when it's configured
        // (falling back to Gemini on any Langflow failure), otherwise Gemini.
        $this->app->bind(SessionAnalyzer::class, function ($app) {
            $gemini = $app->make(GeminiSessionAnalyzer::class);
            $langflow = $app->make(LangflowSessionAnalyzer::class);

            return $langflow->isConfigured() ? new FallbackSessionAnalyzer($langflow, $gemini) : $gemini;
        });

        // PRONUNCIATION_CHECKER_DRIVER=stt_match turns off the ML microservice
        // entirely (no HTTP call attempted at all, not even one that fails) —
        // useful if pronunciation-service turns out too unstable to run.
        // Default 'ml' still degrades to SttMatchPronunciationChecker per-request
        // on its own if the service is unreachable (see MlPronunciationChecker).
        $this->app->bind(PronunciationChecker::class, function ($app) {
            return match (config('services.pronunciation_service.driver')) {
                'stt_match' => $app->make(SttMatchPronunciationChecker::class),
                default => $app->make(MlPronunciationChecker::class),
            };
        });

        // "Raba lalu dengar": Gemini vision when a key is set, otherwise a
        // recognizer that always answers tidak_yakin instead of guessing.
        $this->app->bind(ObjectRecognizer::class, function ($app) {
            return filled(config('services.gemini.key'))
                ? $app->make(GeminiObjectRecognizer::class)
                : $app->make(NullObjectRecognizer::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('voice-command', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('scan-benda', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('scan.rate_limit_per_minute', 6)))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
