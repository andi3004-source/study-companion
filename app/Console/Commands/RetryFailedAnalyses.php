<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeSessionJob;
use App\Models\ConversationSession;
use Illuminate\Console\Command;

/**
 * Re-queues the error analysis for Mandarin sessions where it was tried and
 * failed (analyzed_at set, error_analysis NULL — see AnalyzeSessionJob).
 * analyzed_at is cleared first, because the job skips any session that
 * already has it. Successful analyses (including "no mistakes") are never
 * touched. Like any queued job, this needs `php artisan queue:work` running
 * unless QUEUE_CONNECTION=sync, and each retry spends one AI call.
 */
class RetryFailedAnalyses extends Command
{
    protected $signature = 'analysis:retry-failed {--dry-run : Only list the sessions that would be retried}';

    protected $description = 'Re-queue the error analysis of Mandarin sessions whose analysis failed';

    public function handle(): int
    {
        $sessions = ConversationSession::where('lang', 'mandarin')
            ->whereNotNull('analyzed_at')
            ->whereNull('error_analysis')
            ->orderBy('id')
            ->get(['id', 'user_id', 'ended_at', 'analyzed_at']);

        if ($sessions->isEmpty()) {
            $this->info('No failed analyses to retry.');

            return self::SUCCESS;
        }

        $this->table(
            ['session id', 'user id', 'ended_at', 'failed at'],
            $sessions->map(fn (ConversationSession $s) => [$s->id, $s->user_id, $s->ended_at, $s->analyzed_at])->all()
        );

        if ($this->option('dry-run')) {
            $this->info("{$sessions->count()} session(s) would be re-queued. Dry run: nothing changed.");

            return self::SUCCESS;
        }

        foreach ($sessions as $session) {
            $session->update(['analyzed_at' => null]);
            AnalyzeSessionJob::dispatch($session->id);
        }

        $this->info("{$sessions->count()} session(s) re-queued. Run `php artisan queue:work` if no worker is running.");

        return self::SUCCESS;
    }
}
