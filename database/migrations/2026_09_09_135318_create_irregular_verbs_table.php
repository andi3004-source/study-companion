<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irregular_verbs', function (Blueprint $table) {
            $table->id();
            $table->string('base_form');
            $table->string('past_form');
            $table->string('past_participle');
            $table->string('meaning_id');
            $table->string('example_sentence');
            $table->unsignedTinyInteger('batch_number'); // 1..7, 5 verbs each
            $table->unsignedInteger('order_in_batch');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irregular_verbs');
    }
};
