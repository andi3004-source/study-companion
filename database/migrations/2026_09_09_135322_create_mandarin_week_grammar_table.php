<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandarin_week_grammar', function (Blueprint $table) {
            $table->id();
            $table->string('week_letter', 1); // A/B/C/D
            $table->text('grammar_text');
            $table->text('listening_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandarin_week_grammar');
    }
};
