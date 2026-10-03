<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference dictionary for HSK2-6 (HSK1 stays in mandarin_words, which
     * drives the Dashboard's day-by-day curriculum and must keep its existing
     * batch_number/order_in_batch and row ids intact for user_word_progress).
     */
    public function up(): void
    {
        Schema::create('mandarin_hsk_words', function (Blueprint $table) {
            $table->id();
            $table->string('hanzi');
            $table->string('traditional')->nullable();
            $table->string('pinyin');
            $table->string('meaning_en');
            $table->string('meaning_id')->nullable(); // filled by the translation job later
            $table->json('pos')->nullable();
            $table->string('radical')->nullable();
            $table->unsignedInteger('frequency')->nullable();
            $table->unsignedTinyInteger('hsk_level_old');
            $table->unsignedTinyInteger('hsk_level_new')->nullable();
            $table->json('classifiers')->nullable();
            $table->timestamps();

            $table->unique(['hanzi', 'pinyin']);
            $table->index('hsk_level_old');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandarin_hsk_words');
    }
};
