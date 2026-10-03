<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generic_tips', function (Blueprint $table) {
            $table->id();
            $table->string('item_type')->unique(); // review|quiz|dictation|notes|mock|listening|reading
            $table->text('tip_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generic_tips');
    }
};
