<?php

namespace Tests\Concerns;

/**
 * Small closed list for "Raba lalu dengar" tests, in the same shape as
 * storage/app/seed-data/daftar_benda_tertutup.json. Call useScanCatalog()
 * in setUp() and removeScanCatalog() in tearDown().
 */
trait UsesScanCatalog
{
    protected string $scanCatalogPath;

    protected function useScanCatalog(): void
    {
        $this->scanCatalogPath = sys_get_temp_dir().'/scan-katalog-'.uniqid().'.json';
        file_put_contents($this->scanCatalogPath, json_encode([
            ['id' => 'cangkir', 'hanzi' => '杯子', 'pinyin' => 'bēizi', 'nama_untuk_prompt' => 'cangkir atau gelas', 'arti_tampil' => 'gelas; cangkir', 'inggris' => 'cup', 'ipa_inggris' => '/kʌp/', 'cefr_level' => 'A1', 'ikut_prompt' => true],
            ['id' => 'sendok', 'hanzi' => '勺子', 'pinyin' => 'sháozi', 'nama_untuk_prompt' => 'sendok', 'arti_tampil' => 'sendok', 'inggris' => 'spoon', 'ipa_inggris' => '/spuːn/', 'cefr_level' => 'A1', 'ikut_prompt' => true],
            ['id' => 'pisau', 'hanzi' => '刀', 'pinyin' => 'dāo', 'nama_untuk_prompt' => 'pisau', 'arti_tampil' => 'pisau', 'inggris' => 'knife', 'ipa_inggris' => '/naɪf/', 'cefr_level' => 'A2', 'ikut_prompt' => false],
        ], JSON_UNESCAPED_UNICODE));
        config(['katabicara.path' => $this->scanCatalogPath]);
    }

    protected function removeScanCatalog(): void
    {
        @unlink($this->scanCatalogPath);
    }
}
