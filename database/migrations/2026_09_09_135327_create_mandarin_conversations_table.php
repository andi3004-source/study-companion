<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandarin_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('week_letter', 1); // A/B/C/D
            $table->unsignedInteger('order_in_week');
            $table->string('q_hanzi');
            $table->string('q_pinyin');
            $table->string('q_id');
            $table->string('a_hanzi');
            $table->string('a_pinyin');
            $table->string('a_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandarin_conversations');
    }
};
