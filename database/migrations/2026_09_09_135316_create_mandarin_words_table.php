<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandarin_words', function (Blueprint $table) {
            $table->id();
            $table->string('hanzi');
            $table->string('pinyin');
            $table->string('meaning_id');
            $table->string('meaning_en');
            $table->unsignedTinyInteger('batch_number'); // 1..21 for HSK1 core set
            $table->unsignedInteger('order_in_batch');
            // forward-compatible with HSK1-6 expansion (Appendix 11), unused for now
            $table->unsignedTinyInteger('hsk_level_old')->nullable();
            $table->unsignedTinyInteger('hsk_level_new')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandarin_words');
    }
};
