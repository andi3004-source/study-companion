<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week_topics', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('week_number'); // 1..9
            $table->unsignedTinyInteger('topic_order'); // 0..2
            $table->string('topic_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_topics');
    }
};
