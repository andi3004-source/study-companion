<?php

namespace App\Providers;

use App\Contracts\AiClient;
use App\Contracts\PronunciationChecker;
use App\Services\GeminiClient;
use App\Services\MlPronunciationChecker;
use App\Services\SttMatchPronunciationChecker;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
