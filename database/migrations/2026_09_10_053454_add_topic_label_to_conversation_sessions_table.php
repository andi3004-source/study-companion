<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Only set for scenario_key='random' sessions — the randomly-assigned opening topic. */
    public function up(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->string('topic_label')->nullable()->after('scenario_key');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->dropColumn('topic_label');
        });
    }
};
