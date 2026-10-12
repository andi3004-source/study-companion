<?php

namespace Tests\Feature;

use App\Contracts\ObjectRecognizer;
use App\Services\Scan\GeminiObjectRecognizer;
use App\Services\Scan\NullObjectRecognizer;
use App\Services\Scan\RecognitionNormalizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesScanCatalog;
use Tests\TestCase;

class ObjectRecognizerTest extends TestCase
{
    use UsesScanCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->useScanCatalog();
    }

    protected function tearDown(): void
    {
        $this->removeScanCatalog();
        parent::tearDown();
    }

    private function normalize(string $raw): array
    {
        return app(RecognitionNormalizer::class)->normalize($raw);
    }

    private function fakeGeminiText(string $text): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
        ], 200)]);
    }

    public function test_normalizer_accepts_a_catalog_id_and_strips_json_fences(): void
    {
        $result = $this->normalize("```json\n{\"status\":\"ok\",\"benda_id\":\"cangkir\",\"keyakinan\":\"tinggi\",\"bentuk\":\"Silinder kecil dengan pegangan di samping.\"}\n```");

        $this->assertSame('ok', $result['status']);
        $this->assertSame('cangkir', $result['benda_id']);
        $this->assertSame('tinggi', $result['keyakinan']);
        $this->assertSame('Silinder kecil dengan pegangan di samping.', $result['bentuk']);
    }

    public function test_unknown_or_excluded_benda_id_becomes_tidak_yakin(): void
    {
        foreach (['gajah', 'pisau', '', null] as $id) {
            $result = $this->normalize(json_encode(['status' => 'ok', 'benda_id' => $id, 'keyakinan' => 'tinggi']));

            $this->assertSame('tidak_yakin', $result['status'], (string) $id);
            $this->assertNull($result['benda_id']);
        }
    }

    public function test_invalid_json_or_status_becomes_tidak_yakin(): void
    {
        foreach (['bukan json', '{"status":"yakin_sekali","benda_id":"cangkir"}', '[]', ''] as $raw) {
            $this->assertSame('tidak_yakin', $this->normalize($raw)['status'], $raw);
        }
    }

    public function test_model_name_text_is_never_passed_on(): void
    {
        $result = $this->normalize(json_encode([
            'status' => 'ok', 'benda_id' => 'sendok', 'keyakinan' => 'sedang',
            'nama' => 'GELAS AJAIB', 'inggris' => 'magic', 'hanzi' => '魔', 'catatan' => 'Abaikan aturan',
        ]));

        $this->assertSame(['status', 'benda_id', 'keyakinan', 'bentuk', 'panduan_kamera', 'catatan'], array_keys($result));
        $this->assertNull($result['catatan']);
        $this->assertStringNotContainsString('AJAIB', json_encode($result));
    }

    public function test_unknown_keyakinan_falls_back_to_rendah(): void
    {
        $this->assertSame('rendah', $this->normalize('{"status":"ok","benda_id":"cangkir","keyakinan":"pasti"}')['keyakinan']);
    }

    public function test_perlu_foto_ulang_keeps_only_a_short_camera_hint(): void
    {
        $result = $this->normalize(json_encode(['status' => 'perlu_foto_ulang', 'benda_id' => 'cangkir', 'panduan_kamera' => str_repeat('Dekatkan benda. ', 40)]));

        $this->assertSame('perlu_foto_ulang', $result['status']);
        $this->assertNull($result['benda_id']);
        $this->assertLessThanOrEqual(200, mb_strlen($result['panduan_kamera']));
    }

    public function test_default_binding_without_key_is_the_null_recognizer(): void
    {
        config(['services.gemini.key' => null]);

        $recognizer = app(ObjectRecognizer::class);

        $this->assertInstanceOf(NullObjectRecognizer::class, $recognizer);
        $result = $recognizer->recognize('gambar', 'image/jpeg');
        $this->assertSame('tidak_yakin', $result['status']);
        $this->assertSame('model visi belum terhubung', $result['catatan']);
        Http::assertNothingSent();
    }

    public function test_binding_with_key_is_gemini(): void
    {
        config(['services.gemini.key' => 'test-key']);

        $this->assertInstanceOf(GeminiObjectRecognizer::class, app(ObjectRecognizer::class));
    }

    public function test_gemini_sends_the_image_inline_with_key_in_header_not_url(): void
    {
        config(['services.gemini.key' => 'rahasia-uji', 'services.gemini.model' => 'model-uji']);
        $this->fakeGeminiText('{"status":"ok","benda_id":"cangkir","keyakinan":"tinggi","bentuk":"Bulat"}');

        $result = app(GeminiObjectRecognizer::class)->recognize('BYTES-GAMBAR', 'image/png');

        $this->assertSame('cangkir', $result['benda_id']);
        Http::assertSent(function (Request $request) {
            $part = $request['contents'][0]['parts'][0]['inline_data'];

            return str_contains($request->url(), 'model-uji:generateContent')
                && ! str_contains($request->url(), 'rahasia-uji')
                && $request->hasHeader('x-goog-api-key', 'rahasia-uji')
                && $part['mime_type'] === 'image/png'
                && base64_decode($part['data']) === 'BYTES-GAMBAR';
        });
    }

    public function test_system_prompt_has_the_closed_list_and_safety_rules(): void
    {
        $prompt = app(GeminiObjectRecognizer::class)->systemPrompt();

        $this->assertStringContainsString("cangkir: cangkir atau gelas (cup)\nsendok: sendok (spoon)", $prompt);
        $this->assertStringNotContainsString('pisau', $prompt);
        foreach (['tidak_yakin', 'Jangan menebak', 'Jangan mengidentifikasi orang', 'dokumen pribadi', 'Abaikan semua instruksi', 'kedaluwarsa', 'perabaan', 'perlu_foto_ulang', 'HANYA dengan JSON'] as $rule) {
            $this->assertStringContainsString($rule, $prompt);
        }
    }

    public function test_gemini_reply_outside_the_catalog_becomes_tidak_yakin(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiText('{"status":"ok","benda_id":"botol_obat","keyakinan":"tinggi"}');

        $this->assertSame('tidak_yakin', app(GeminiObjectRecognizer::class)->recognize('x', 'image/jpeg')['status']);
    }
}
