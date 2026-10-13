<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MySQL compatibility that can be checked without a MySQL server: the
 * migration and query source, plus the resulting SQLite schema (tests run on
 * SQLite only). See README "Keterbatasan data".
 */
class MysqlCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $name): string
    {
        return file_get_contents(collect(glob(database_path('migrations/*_'.$name.'.php')))->sole());
    }

    public function test_long_hsk_meanings_are_text_columns(): void
    {
        $source = $this->migration('create_mandarin_hsk_words_table');

        // Source data has meaning_en up to ~470 characters; their translations land in meaning_id.
        $this->assertStringContainsString("\$table->text('meaning_en');", $source);
        $this->assertStringContainsString("\$table->text('meaning_id')->nullable();", $source);
        $this->assertStringNotContainsString("string('meaning_en')", $source);
        $this->assertStringNotContainsString("string('meaning_id')", $source);

        $this->assertSame('text', Schema::getColumnType('mandarin_hsk_words', 'meaning_en'));
        $this->assertSame('text', Schema::getColumnType('mandarin_hsk_words', 'meaning_id'));
    }

    public function test_lower_word_index_uses_the_right_syntax_per_driver(): void
    {
        $source = $this->migration('add_word_lower_index_to_english_vocab_table');

        $this->assertStringContainsString("'mysql' => 'CREATE INDEX english_vocab_word_lower_idx ON english_vocab ((LOWER(word)))'", $source);
        $this->assertStringContainsString("'mariadb' => 'CREATE INDEX english_vocab_word_lower_idx ON english_vocab (word)'", $source);
        $this->assertStringContainsString("default => 'CREATE INDEX english_vocab_word_lower_idx ON english_vocab (LOWER(word))'", $source);
        // MariaDB must never get the functional-index syntax.
        $this->assertDoesNotMatchRegularExpression("/'mariadb'[^\\n]*LOWER\\(word\\)/", $source);

        // On SQLite the expression index still exists, unchanged.
        $sql = DB::table('sqlite_master')->where('name', 'english_vocab_word_lower_idx')->value('sql');
        $this->assertSame('CREATE INDEX english_vocab_word_lower_idx ON english_vocab (LOWER(word))', $sql);
    }

    public function test_no_sqlite_only_sql_in_migrations_or_queries(): void
    {
        $files = [...glob(database_path('migrations/*.php')), ...glob(app_path('{,*/,*/*/}*.php'), GLOB_BRACE)];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents($file));
            foreach (['PRAGMA', 'strftime(', 'julianday(', ' GLOB ', 'instr(', 'AUTOINCREMENT', 'datetime(\'now'] as $sqliteOnly) {
                $this->assertStringNotContainsStringIgnoringCase($sqliteOnly, $code, basename($file).': '.$sqliteOnly);
            }
        }
    }

    public function test_kamus_concatenation_and_json_depend_on_the_driver(): void
    {
        $source = file_get_contents(app_path('Services/KamusSearchService.php'));

        // SQL "||" is OR in MySQL: it may only appear in the non-MySQL branch.
        $this->assertSame(1, substr_count($source, "' ' || "));
        $this->assertStringContainsString("self::isMysql() ? \"CONCAT(' ', {\$cleaned}, ' ')\" : \"(' ' || {\$cleaned} || ' ')\"", $source);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $source);
        $this->assertSame(1, substr_count($source, 'json_extract('));
    }

    public function test_readme_records_the_remaining_differences(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $section = substr($readme, strpos($readme, '## Keterbatasan data'));
        $section = substr($section, 0, strpos($section, "\n## ", 3));

        foreach (['utf8mb4_unicode_ci', '4.991', '63 koreksi', 'utf8mb4_bin', 'Urutan Kamus', 'Aksen di pencarian', 'di luar BMP', 'MariaDB'] as $phrase) {
            $this->assertStringContainsString($phrase, $section, $phrase);
        }
    }
}
