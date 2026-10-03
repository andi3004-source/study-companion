<?php

use App\Services\AnswerChecker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tone-insensitive pinyin search (Kamus) needs to run as a DB-side LIKE
     * over 15k+ rows, which means the diacritic-stripped form must be stored,
     * not computed per-request. Backfilled here once; new/updated rows keep
     * it in sync via MandarinHskSeeder.
     */
    public function up(): void
    {
        Schema::table('mandarin_words', function (Blueprint $table) {
            $table->string('pinyin_normalized')->nullable()->after('pinyin');
        });

        Schema::table('mandarin_hsk_words', function (Blueprint $table) {
            $table->string('pinyin_normalized')->nullable()->after('pinyin');
        });

        $checker = new AnswerChecker;

        foreach (['mandarin_words', 'mandarin_hsk_words'] as $table) {
            DB::table($table)->select('id', 'pinyin')->orderBy('id')->chunkById(500, function ($rows) use ($checker, $table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        'pinyin_normalized' => $checker->normalizePinyin($row->pinyin),
                    ]);
                }
            });
        }

        Schema::table('mandarin_words', function (Blueprint $table) {
            $table->index('pinyin_normalized');
        });
        Schema::table('mandarin_hsk_words', function (Blueprint $table) {
            $table->index('pinyin_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('mandarin_words', function (Blueprint $table) {
            $table->dropColumn('pinyin_normalized');
        });
        Schema::table('mandarin_hsk_words', function (Blueprint $table) {
            $table->dropColumn('pinyin_normalized');
        });
    }
};
