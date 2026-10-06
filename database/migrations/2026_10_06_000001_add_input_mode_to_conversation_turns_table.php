<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** How a user turn was entered: 'text' (typed) or 'voice' (STT transcript). Assistant turns are always 'text'. */
    public function up(): void
    {
        Schema::table('conversation_turns', function (Blueprint $table) {
            $table->string('input_mode')->default('text')->after('translation');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_turns', function (Blueprint $table) {
            $table->dropColumn('input_mode');
        });
    }
};
