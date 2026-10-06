<?php

namespace Tests\Unit;

use App\Enums\ErrorCategory;
use App\Support\ErrorGameItems;
use Tests\TestCase;

class ErrorGameItemsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ErrorGameItems::flush();
        config(['game.items_path' => base_path('tests/Fixtures/kesalahan_game.json')]);
    }

    public function test_parses_the_fixture_and_skips_malformed_entries(): void
    {
        $items = ErrorGameItems::all();

        $this->assertCount(12, $items); // entry 13 has no 'benar'
        $this->assertNull(ErrorGameItems::find(13));

        $item = ErrorGameItems::find('1'); // ids are integers; string lookups are cast
        $this->assertSame(1, $item['id']);
        $this->assertSame('bu_atau_mei', $item['kategori']);
        $this->assertSame('我不有钱。', $item['salah']);
        $this->assertSame('我没有钱。', $item['benar']);
        $this->assertSame('Saya tidak punya uang.', $item['arti_id']);
        $this->assertSame([], $item['hanzi_di_luar_hsk1']);
        $this->assertSame(['只'], ErrorGameItems::find(10)['hanzi_di_luar_hsk1']);
    }

    public function test_unknown_category_becomes_lainnya(): void
    {
        $this->assertSame('lainnya', ErrorGameItems::find(12)['kategori']);
    }

    public function test_draft_status_is_recognized(): void
    {
        $this->assertTrue(ErrorGameItems::isDraft(ErrorGameItems::find(3)));
        $this->assertFalse(ErrorGameItems::isDraft(ErrorGameItems::find(1)));
    }

    public function test_categories_are_only_those_with_questions(): void
    {
        $categories = ErrorGameItems::categories();
        sort($categories);

        $this->assertSame(['bu_atau_mei', 'kata_penggolong', 'kosakata', 'lainnya', 'shi_dengan_kata_sifat', 'urutan_kata'], $categories);
        $this->assertNotContains('nada', $categories); // only the malformed entry had it
    }

    public function test_missing_file_means_no_items(): void
    {
        config(['game.items_path' => base_path('tests/Fixtures/does_not_exist.json')]);

        $this->assertFalse(ErrorGameItems::exists());
        $this->assertSame([], ErrorGameItems::all());
    }

    public function test_the_real_question_bank_has_27_valid_items(): void
    {
        config(['game.items_path' => storage_path('app/seed-data/kesalahan_game.json')]);

        if (! ErrorGameItems::exists()) {
            $this->markTestSkipped('storage/app/seed-data/kesalahan_game.json is not there yet.');
        }

        $items = ErrorGameItems::all();

        $this->assertCount(27, $items);
        foreach ($items as $item) {
            $this->assertContains($item['kategori'], ErrorCategory::values());
            $this->assertNotSame('', $item['salah']);
            $this->assertNotSame('', $item['benar']);
        }
    }
}
