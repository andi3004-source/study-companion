<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate from irregular_verbs, which drives the existing 7-day/5-verb
     * English curriculum in Scheduler. This is a browsable 183-verb reference
     * set with no batch structure and no example sentences of its own.
     */
    public function up(): void
    {
        Schema::create('irregular_verbs_reference', function (Blueprint $table) {
            $table->id();
            $table->string('base_form');
            $table->string('past_form');
            $table->string('past_participle');
            $table->string('ing_form');
            $table->string('meaning_id');
            $table->unsignedInteger('frequency_rank');
            $table->timestamps();

            $table->unique('base_form');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irregular_verbs_reference');
    }
};
