<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ielts_vocab', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->string('word_en');
            $table->string('meaning_id');
            $table->string('example_sentence');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ielts_vocab');
    }
};
