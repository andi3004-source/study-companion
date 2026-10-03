<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mandarin_words', function (Blueprint $table) {
            $table->string('traditional')->nullable()->after('hanzi');
            $table->string('radical')->nullable()->after('meaning_en');
            $table->unsignedInteger('frequency')->nullable()->after('radical');
            $table->json('classifiers')->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('mandarin_words', function (Blueprint $table) {
            $table->dropColumn(['traditional', 'radical', 'frequency', 'classifiers']);
        });
    }
};
