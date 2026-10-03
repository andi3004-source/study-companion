<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('past_tense_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('slot_number'); // 0..14
            $table->string('title');
            $table->text('detail');
            $table->unsignedTinyInteger('batch_number_ref')->nullable(); // maps to irregular_verbs.batch_number
            $table->string('platform_link_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_tense_steps');
    }
};
