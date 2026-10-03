<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('english_vocab', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('external_id')->unique(); // source dataset's own id, used for idempotent re-seeding
            $table->string('word');
            $table->string('pos')->nullable();
            $table->text('definition')->nullable();
            $table->string('hint')->nullable();
            $table->string('cefr_level', 2);
            $table->json('categories')->nullable();
            $table->json('examples')->nullable();
            $table->json('translations')->nullable(); // th/zh/es/fr/ja/ko + id (filled by the translation job later)
            $table->string('ipa')->nullable();
            $table->json('synonyms')->nullable();
            $table->timestamps();

            $table->index('cefr_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_vocab');
    }
};
