<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_word_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('word_type', ['mandarin', 'verb', 'vocab']);
            $table->unsignedBigInteger('word_id');
            $table->boolean('is_known')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'word_type', 'word_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_word_progress');
    }
};
