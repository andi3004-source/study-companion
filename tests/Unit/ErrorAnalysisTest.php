<?php

namespace Tests\Unit;

use App\Enums\ErrorCategory;
use App\Support\ErrorAnalysis;
use RuntimeException;
use Tests\TestCase;

class ErrorAnalysisTest extends TestCase
{
    public function test_every_category_has_its_display_label(): void
    {
        $this->assertSame(
            ['urutan_kata', 'shi_dengan_kata_sifat', 'bu_atau_mei', 'kata_penggolong', 'nada', 'pelafalan', 'kosakata', 'lainnya'],
            ErrorCategory::values()
        );
        $this->assertSame('是 dengan kata sifat', ErrorCategory::ShiDenganKataSifat->label());
        $this->assertSame('不 atau 没', ErrorCategory::BuAtauMei->label());
        $this->assertSame('Pilihan kata', ErrorCategory::Kosakata->label());
    }

    public function test_unknown_or_missing_category_becomes_lainnya(): void
    {
        $this->assertSame(ErrorCategory::Lainnya, ErrorCategory::fromLoose('grammar_umum'));
        $this->assertSame(ErrorCategory::Lainnya, ErrorCategory::fromLoose(null));
        $this->assertSame(ErrorCategory::Nada, ErrorCategory::fromLoose(' NADA '));
    }

    public function test_parses_fenced_json_and_normalizes_items(): void
    {
        $result = ErrorAnalysis::parse("```json\n".json_encode([
            'items' => [
                ['kategori' => 'bu_atau_mei', 'contoh' => '我不有钱', 'koreksi' => '我没有钱', 'penjelasan' => '有 diingkari dengan 没.'],
                ['kategori' => 'tata_bahasa', 'contoh' => '我是高兴', 'koreksi' => '我很高兴', 'penjelasan' => 'Kata sifat tanpa 是.'],
                ['kategori' => 'nada', 'contoh' => '   '], // no example: dropped
            ],
            'catatan' => 'Bagus!',
            'usage' => ['tokens_in' => 1],
        ], JSON_UNESCAPED_UNICODE)."\n```");

        $this->assertSame([
            'items' => [
                ['kategori' => 'bu_atau_mei', 'contoh' => '我不有钱', 'koreksi' => '我没有钱', 'penjelasan' => '有 diingkari dengan 没.'],
                ['kategori' => 'lainnya', 'contoh' => '我是高兴', 'koreksi' => '我很高兴', 'penjelasan' => 'Kata sifat tanpa 是.'],
            ],
            'catatan' => 'Bagus!',
        ], $result);
    }

    public function test_no_mistakes_is_an_empty_item_list_not_null(): void
    {
        $this->assertSame(['items' => [], 'catatan' => 'Tidak ada kesalahan.'], ErrorAnalysis::parse('{"items": [], "catatan": "Tidak ada kesalahan."}'));
    }

    public function test_caps_items_at_five(): void
    {
        $items = array_fill(0, 8, ['kategori' => 'nada', 'contoh' => '妈']);

        $this->assertCount(5, ErrorAnalysis::normalize(['items' => $items])['items']);
    }

    public function test_sumber_is_kept_only_when_valid_and_never_added(): void
    {
        $this->assertSame('langflow', ErrorAnalysis::normalize(['items' => [], 'sumber' => 'langflow'])['sumber']);
        $this->assertSame('gemini', ErrorAnalysis::normalize(['items' => [], 'sumber' => 'gemini'])['sumber']);
        $this->assertArrayNotHasKey('sumber', ErrorAnalysis::normalize(['items' => [], 'sumber' => 'chatgpt']));
        $this->assertSame(['items' => [], 'catatan' => null], ErrorAnalysis::normalize(['items' => []]));
    }

    public function test_the_model_cannot_set_sumber(): void
    {
        $this->assertArrayNotHasKey('sumber', ErrorAnalysis::parse('{"items":[],"catatan":null,"sumber":"gemini"}'));
    }

    public function test_broken_json_throws(): void
    {
        $this->expectException(RuntimeException::class);

        ErrorAnalysis::parse('{"items": [');
    }
}
