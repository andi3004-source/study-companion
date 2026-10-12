<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * KamusSearchService LEFT JOINs english_vocab to irregular_verbs_reference
     * on LOWER(word) = base_form (both directions) to merge V1/V2/V3 into the
     * same search row. Without this expression index, SQLite falls back to a
     * full table scan of english_vocab per irregular-verb row instead of an
     * index search, which is fine driving from the 10k-row side but very slow
     * (~2s) driving from the 183-row side.
     */
    public function up(): void
    {
        // MySQL 8.0.13+ needs the expression in its own parentheses (a functional key part).
        DB::statement($this->isMysql()
            ? 'CREATE INDEX english_vocab_word_lower_idx ON english_vocab ((LOWER(word)))'
            : 'CREATE INDEX english_vocab_word_lower_idx ON english_vocab (LOWER(word))');
    }

    public function down(): void
    {
        DB::statement($this->isMysql()
            ? 'DROP INDEX english_vocab_word_lower_idx ON english_vocab'
            : 'DROP INDEX IF EXISTS english_vocab_word_lower_idx');
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
