<?php

namespace Tests\Feature;

use App\Console\Commands\LangflowPing;
use App\Support\SecretRedactor;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LangflowPingCommandTest extends TestCase
{
    private const RUN_URL = 'https://langflow.test/api/v1/run/flow-123?stream=false';

    private const API_KEY = 'lf-FAKE-key-0123456789abcdef';

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.langflow' => ['url' => 'https://langflow.test/api/', 'flow_id' => 'flow-123', 'api_key' => self::API_KEY, 'timeout' => 120]]);
        Http::preventStrayRequests();

        // --dump-structure writes into storage/app; keep the real one untouched.
        $this->storage = sys_get_temp_dir().'/langflow-ping-test-'.uniqid();
        mkdir($this->storage.'/app', recursive: true);
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->storage);

        parent::tearDown();
    }

    private function runResponse(string $text): array
    {
        return ['outputs' => [['outputs' => [['results' => ['message' => ['text' => $text]]]]]]];
    }

    public function test_config_shows_the_setup_without_the_key_or_a_network_call(): void
    {
        Http::fake();

        $this->artisan('langflow:ping', ['--config' => true])
            ->expectsOutputToContain('LANGFLOW_URL      : terisi')
            ->expectsOutputToContain('LANGFLOW_FLOW_ID  : terisi')
            ->expectsOutputToContain('LANGFLOW_API_KEY  : terisi, panjang '.strlen(self::API_KEY))
            ->expectsOutputToContain('Timeout           : 120 detik')
            ->expectsOutputToContain('URL akhir         : '.self::RUN_URL)
            ->expectsOutputToContain('tidak ada panggilan jaringan')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->doesntExpectOutputToContain('KUOTA')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_config_warns_about_an_empty_key(): void
    {
        config(['services.langflow.api_key' => null]);
        Http::fake();

        $this->artisan('langflow:ping', ['--config' => true])
            ->expectsOutputToContain('LANGFLOW_API_KEY  : kosong')
            ->expectsOutputToContain('akan menolak dengan HTTP 401/403')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_unconfigured_langflow_is_never_called(): void
    {
        config(['services.langflow.flow_id' => null]);
        Http::fake();

        $this->artisan('langflow:ping')
            ->expectsOutputToContain('URL akhir         : (belum lengkap)')
            ->expectsOutputToContain('Langflow tidak dipanggil')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_ping_sends_the_fixed_transcript_and_prints_the_result(): void
    {
        Http::fake([self::RUN_URL => Http::response($this->runResponse('{"items":[{"kategori":"shi_dengan_kata_sifat","contoh":"我是高兴","koreksi":"我很高兴","penjelasan":"..."}],"catatan":"ok"}'))]);

        $this->artisan('langflow:ping')
            ->expectsOutputToContain('MEMAKAI KUOTA MODEL')
            ->expectsOutputToContain('Status HTTP: 200')
            ->expectsOutputToContain('Waktu: ')
            ->expectsOutputToContain('我很高兴')
            ->expectsOutputToContain('Teks berhasil dibaca sebagai analisis: 1 item.')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['input_value'] === "AI: 你好！\nUser: 我是高兴。"
            && str_starts_with($request['session_id'], 'analisis-manual-'));
        $this->assertFileDoesNotExist(storage_path(LangflowPing::DUMP_FILE));
    }

    public function test_ping_prints_a_redacted_error(): void
    {
        Http::fake([self::RUN_URL => Http::response(['detail' => 'Bad key '.self::API_KEY.' or AIzaFAKEgoogle0123456789abcd'], 500)]);

        $this->artisan('langflow:ping')
            ->expectsOutputToContain('Status HTTP: 500')
            ->expectsOutputToContain('Langflow HTTP 500: {"detail":"Bad key [REDACTED] or [REDACTED]"}')
            ->expectsOutputToContain('Jenis: permanen')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->doesntExpectOutputToContain('AIzaFAKE')
            ->assertFailed();
    }

    public function test_ping_reports_a_connection_failure(): void
    {
        Http::fake([self::RUN_URL => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

        $this->artisan('langflow:ping')
            ->expectsOutputToContain('Gagal terhubung setelah')
            ->assertFailed();
    }

    public function test_dump_structure_prints_a_redacted_key_tree_and_saves_the_redacted_response(): void
    {
        $long = str_repeat('长', 100);
        Http::fake([self::RUN_URL => Http::response([
            'session_id' => 'analisis-manual-x',
            'outputs' => [['outputs' => [[
                'results' => ['message' => ['text' => '{"items":[],"catatan":null}', 'echo' => 'key='.self::API_KEY]],
                'artifacts' => ['long' => $long, 'count' => 3, 'frozen' => false, 'empty' => []],
            ]]]],
        ])]);

        $this->artisan('langflow:ping', ['--dump-structure' => true])
            ->expectsOutputToContain('Struktur respons')
            ->expectsOutputToContain('outputs:')
            ->expectsOutputToContain('[0]:')
            ->expectsOutputToContain('      results:')
            ->expectsOutputToContain('echo: "key=[REDACTED]"')
            ->expectsOutputToContain('long: "'.str_repeat('长', 80).'…"')
            ->expectsOutputToContain('count: 3')
            ->expectsOutputToContain('frozen: false')
            ->expectsOutputToContain('empty: (kosong)')
            ->expectsOutputToContain('langflow-last-response.json')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->assertSuccessful();

        $saved = file_get_contents(storage_path(LangflowPing::DUMP_FILE));
        $this->assertStringNotContainsString(self::API_KEY, $saved);
        $this->assertStringContainsString(SecretRedactor::MASK, $saved);
        $this->assertStringContainsString($long, $saved); // the saved copy is complete, only redacted
        $this->assertSame('{"items":[],"catatan":null}', json_decode($saved, true)['outputs'][0]['outputs'][0]['results']['message']['text']);
    }

    public function test_dump_structure_also_works_for_an_error_response(): void
    {
        Http::fake([self::RUN_URL => Http::response(['detail' => 'model overloaded'], 503)]);

        $this->artisan('langflow:ping', ['--dump-structure' => true])
            ->expectsOutputToContain('Jenis: sementara')
            ->expectsOutputToContain('detail: "model overloaded"')
            ->assertFailed();

        $this->assertFileExists(storage_path(LangflowPing::DUMP_FILE));
    }
}
