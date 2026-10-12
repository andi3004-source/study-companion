<?php

namespace Tests\Feature;

use App\Services\KataBicaraCatalog;
use Tests\Concerns\UsesScanCatalog;
use Tests\TestCase;

class ScanCatalogTest extends TestCase
{
    use UsesScanCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useScanCatalog();
    }

    protected function tearDown(): void
    {
        $this->removeScanCatalog();
        parent::tearDown();
    }

    public function test_prompt_list_has_one_line_per_prompt_entry(): void
    {
        $this->assertSame(
            "cangkir: cangkir atau gelas (cup)\nsendok: sendok (spoon)",
            app(KataBicaraCatalog::class)->promptList()
        );
    }

    public function test_prompt_list_skips_entries_not_in_the_prompt(): void
    {
        $this->assertStringNotContainsString('pisau', app(KataBicaraCatalog::class)->promptList());
    }

    public function test_find_only_returns_entries_from_the_closed_list(): void
    {
        $catalog = app(KataBicaraCatalog::class);

        $this->assertSame('杯子', $catalog->find('cangkir')['hanzi']);
        $this->assertNull($catalog->find('pisau'));
        $this->assertNull($catalog->find('gajah'));
        $this->assertNull($catalog->find(null));
    }

    public function test_missing_catalog_gives_an_empty_prompt_list(): void
    {
        config(['katabicara.path' => sys_get_temp_dir().'/tidak-ada-'.uniqid().'.json']);

        $this->assertSame('', app(KataBicaraCatalog::class)->promptList());
    }

    public function test_scan_config_defaults(): void
    {
        $config = require config_path('scan.php');

        $this->assertFalse($config['enabled']);
        $this->assertSame(4000000, $config['max_image_bytes']);
        $this->assertSame(6, $config['rate_limit_per_minute']);
        $this->assertSame(30, $config['timeout']);
    }
}
