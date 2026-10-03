<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('email');
            $table->string('track')->default('both')->after('start_date'); // mandarin|english|both
            $table->boolean('onboarded')->default(false)->after('track');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'track', 'onboarded']);
        });
    }
};
