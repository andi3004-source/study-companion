<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mandarin sessions only, filled by AnalyzeSessionJob after the session
     * ends. analyzed_at NULL = not analyzed (yet); analyzed_at set +
     * error_analysis NULL = tried and failed (see `analysis:retry-failed`);
     * both set = done, items may be empty when there were no mistakes.
     */
    public function up(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->json('error_analysis')->nullable()->after('summary');
            $table->timestamp('analyzed_at')->nullable()->after('error_analysis');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->dropColumn(['error_analysis', 'analyzed_at']);
        });
    }
};
