<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Indonesian translation of assistant turns in Mandarin sessions only; null otherwise. */
    public function up(): void
    {
        Schema::table('conversation_turns', function (Blueprint $table) {
            $table->text('translation')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_turns', function (Blueprint $table) {
            $table->dropColumn('translation');
        });
    }
};
