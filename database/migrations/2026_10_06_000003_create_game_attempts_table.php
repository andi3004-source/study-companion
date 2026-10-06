<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per "Pemburu Kesalahan" answer. kategori is the QUESTION's
     * category (not what the player picked), so wrong answers per kategori
     * mean "this kind of mistake is hard to spot". Kept apart from
     * conversation_sessions.error_analysis on purpose: that's mistakes the
     * learner made, this is mistakes the learner failed to recognize.
     */
    public function up(): void
    {
        Schema::create('game_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_id'); // id from kesalahan_game.json (an integer there; cast back on read)
            $table->string('kategori');
            $table->boolean('benar');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_attempts');
    }
};
