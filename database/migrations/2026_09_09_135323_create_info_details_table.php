<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('info_details', function (Blueprint $table) {
            $table->id();
            $table->string('label_key'); // e.g. "Kenalan Pinyin (initial & final)"
            $table->text('detail_text');
            $table->unsignedTinyInteger('day_number'); // 1 or 2 (fondasi pinyin day)
            $table->unsignedInteger('order_in_day');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('info_details');
    }
};
