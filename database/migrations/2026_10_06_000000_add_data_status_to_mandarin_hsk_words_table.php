<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NULL = straight from mandarin_hsk1to6_complete.json; 'corrected_draft' =
     * pinyin/meaning_id replaced from hsk1to3_kosakata_id.json (see
     * `php artisan hsk:apply-corrections`) and not yet reviewed.
     */
    public function up(): void
    {
        Schema::table('mandarin_hsk_words', function (Blueprint $table) {
            $table->string('data_status')->nullable()->after('meaning_id');
        });
    }

    public function down(): void
    {
        Schema::table('mandarin_hsk_words', function (Blueprint $table) {
            $table->dropColumn('data_status');
        });
    }
};
