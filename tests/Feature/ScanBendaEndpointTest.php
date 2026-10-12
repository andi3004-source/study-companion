<?php

namespace Tests\Feature;

use App\Contracts\ObjectRecognizer;
use App\Models\User;
use App\Services\Scan\ScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\UsesScanCatalog;
use Tests\TestCase;

class ScanBendaEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesScanCatalog;

    /** A real 1x1 PNG, plus a marker so we can look for it in logs and storage. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->useScanCatalog();
        config(['scan.enabled' => true, 'scan.rate_limit_per_minute' => 6]);
    }

    protected function tearDown(): void
    {
        $this->removeScanCatalog();
        parent::tearDown();
    }

    private function user(): User
    {
        return User::factory()->create(['onboarded' => true, 'start_date' => now()->toDateString(), 'track' => 'both']);
    }

    private function fakeRecognizer(array $result): void
    {
        $this->app->instance(ObjectRecognizer::class, new class($result) implements ObjectRecognizer
        {
            public function __construct(private array $result) {}

            public function recognize(string $imageBytes, string $mime): array
            {
                return $this->result + ['benda_id' => null, 'keyakinan' => 'rendah', 'bentuk' => null, 'panduan_kamera' => null, 'catatan' => null];
            }
        });
    }

    private function send(array $body = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user())
            ->postJson(route('scan.recognize'), $body + ['consent' => true, 'image' => self::PNG_BASE64]);
    }

    public function test_consent_is_required(): void
    {
        $this->fakeRecognizer(['status' => 'ok', 'benda_id' => 'cangkir', 'keyakinan' => 'tinggi']);

        $this->send(['consent' => false])->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->actingAs($this->user())->postJson(route('scan.recognize'), ['image' => self::PNG_BASE64])->assertStatus(422);
    }

    public function test_too_large_image_is_rejected(): void
    {
        config(['scan.max_image_bytes' => 10]);

        $this->send()->assertStatus(422)->assertJsonPath('pesan', 'Foto terlalu besar. Coba foto lagi.');
    }

    public function test_non_image_or_unsupported_mime_is_rejected(): void
    {
        $this->send(['image' => base64_encode('<?php echo "bukan gambar";')])->assertStatus(422)
            ->assertJsonPath('pesan', 'Format foto harus JPEG, PNG, atau WebP.');
        $this->send(['image' => base64_encode("GIF89a\x01\x00\x01\x00\x00\x00\x00;")])->assertStatus(422);
        $this->send(['image' => '%%%bukan-base64%%%'])->assertStatus(422);
    }

    public function test_high_confidence_answer_uses_catalog_names(): void
    {
        $this->fakeRecognizer(['status' => 'ok', 'benda_id' => 'cangkir', 'keyakinan' => 'tinggi', 'bentuk' => 'Silinder dengan pegangan.']);

        $this->send()->assertOk()->assertExactJson([
            'jenis' => 'pasti',
            'pesan' => 'Ini cangkir atau gelas.',
            'benda' => ['indonesia' => 'cangkir atau gelas', 'inggris' => 'cup', 'ipa_inggris' => '/kʌp/', 'hanzi' => '杯子', 'pinyin' => 'bēizi'],
            'bentuk' => 'Silinder dengan pegangan.',
        ]);
    }

    public function test_medium_or_low_confidence_is_only_a_maybe(): void
    {
        foreach (['sedang', 'rendah'] as $keyakinan) {
            $this->fakeRecognizer(['status' => 'ok', 'benda_id' => 'sendok', 'keyakinan' => $keyakinan]);

            $this->send()->assertOk()
                ->assertJsonPath('jenis', 'mungkin')
                ->assertJsonPath('pesan', 'Mungkin sendok. '.ScanService::PESAN_MUNGKIN)
                ->assertJsonPath('benda.hanzi', '勺子');
        }
    }

    public function test_unsure_and_retake_give_friendly_messages(): void
    {
        $this->fakeRecognizer(['status' => 'tidak_yakin']);
        $this->send()->assertOk()->assertJsonPath('jenis', 'tidak_yakin')->assertJsonPath('pesan', ScanService::PESAN_TIDAK_YAKIN);

        $this->fakeRecognizer(['status' => 'perlu_foto_ulang', 'panduan_kamera' => 'Nyalakan lampu.']);
        $this->send()->assertOk()->assertJsonPath('jenis', 'foto_ulang')->assertJsonPath('panduan_kamera', 'Nyalakan lampu.');
    }

    public function test_without_a_vision_key_the_null_recognizer_answers_unsure(): void
    {
        config(['services.gemini.key' => null]);

        $this->send()->assertOk()
            ->assertJsonPath('jenis', 'tidak_yakin')
            ->assertJsonPath('catatan', 'model visi belum terhubung');
        Http::assertNothingSent();
    }

    public function test_recognizer_failure_is_a_friendly_503_and_logs_no_details(): void
    {
        Log::spy();
        $this->app->instance(ObjectRecognizer::class, new class implements ObjectRecognizer
        {
            public function recognize(string $imageBytes, string $mime): array
            {
                throw new RuntimeException('429 high demand https://example.test/?key=rahasia');
            }
        });

        $this->send()->assertStatus(503)->assertJsonPath('pesan', 'Layanan AI sedang penuh, coba lagi sebentar.');

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => ! str_contains(json_encode($context), 'rahasia')
            && ! str_contains(json_encode($context), self::PNG_BASE64))->once();
    }

    public function test_requests_are_throttled(): void
    {
        config(['scan.rate_limit_per_minute' => 2]);
        $this->fakeRecognizer(['status' => 'tidak_yakin']);
        $user = $this->user();

        $this->send([], $user)->assertOk();
        $this->send([], $user)->assertOk();
        $this->send([], $user)->assertStatus(429);
    }

    public function test_no_image_file_is_left_in_storage(): void
    {
        $this->fakeRecognizer(['status' => 'ok', 'benda_id' => 'cangkir', 'keyakinan' => 'tinggi']);
        $before = collect(File::allFiles(storage_path()))->map->getPathname()->all();

        $this->send()->assertOk();

        $new = array_diff(collect(File::allFiles(storage_path()))->map->getPathname()->all(), $before);
        $this->assertSame([], array_values($new));
        $this->assertDirectoryDoesNotExist(storage_path('app/livewire-tmp/'.date('Y-m-d')));
    }

    public function test_disabled_flag_gives_404(): void
    {
        config(['scan.enabled' => false]);

        $this->send()->assertNotFound();
    }

    public function test_guests_cannot_use_the_endpoint(): void
    {
        $this->postJson(route('scan.recognize'), ['consent' => true, 'image' => self::PNG_BASE64])->assertUnauthorized();
    }
}
