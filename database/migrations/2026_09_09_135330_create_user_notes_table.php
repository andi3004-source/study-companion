<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('day_number');
            $table->enum('lang', ['mandarin', 'english']);
            $table->string('item_index'); // e.g. "writing", "speaking-part1", "speaking-conversation"
            $table->text('note_text')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'day_number', 'lang', 'item_index'], 'user_notes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notes');
    }
};
