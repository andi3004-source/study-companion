<?php

namespace App\Console\Commands;

use App\Exceptions\LangflowRequestException;
use App\Services\LangflowSessionAnalyzer;
use App\Support\ErrorAnalysis;
use App\Support\SecretRedactor;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Diagnoses the Langflow connection used by the error analysis. --config
 * only shows what is configured (the key as "terisi, panjang N", never its
 * value) and makes no network call. Without it, ONE fixed short transcript
 * is sent to the flow — a real model call that spends quota — and the
 * status, time and extracted text are printed. --dump-structure also prints
 * the response's key tree and saves the whole response, redacted, to
 * storage/app/langflow-last-response.json for use as a test fixture
 * (tests/Fixtures/langflow_response.json). Everything printed or saved goes
 * through SecretRedactor first.
 */
class LangflowPing extends Command
{
    public const TRANSCRIPT = "AI: 你好！\nUser: 我是高兴。";

    public const DUMP_FILE = 'app/langflow-last-response.json';

    private const DUMP_STRING_LIMIT = 80;

    protected $signature = 'langflow:ping
        {--config : Only show the configuration; no network call}
        {--dump-structure : Also print the response key tree and save the redacted response}';

    protected $description = 'Check the Langflow error-analysis connection (spends model quota unless --config)';

    public function handle(): int
    {
        $analyzer = app(LangflowSessionAnalyzer::class);

        $this->showConfig($analyzer);

        if ($this->option('config')) {
            $this->info('Hanya konfigurasi: tidak ada panggilan jaringan.');

            return self::SUCCESS;
        }

        if (! $analyzer->isConfigured()) {
            $this->error('LANGFLOW_URL / LANGFLOW_FLOW_ID belum diisi; Langflow tidak dipanggil.');

            return self::FAILURE;
        }

        $this->warn('Perhatian: perintah ini memanggil Langflow sungguhan dan MEMAKAI KUOTA MODEL (satu panggilan).');

        $started = microtime(true);

        try {
            $response = $analyzer->send(self::TRANSCRIPT, LangflowSessionAnalyzer::newSessionId());
        } catch (ConnectionException $e) {
            $this->error('Gagal terhubung setelah '.$this->elapsed($started).': '.SecretRedactor::redact($e->getMessage()));

            return self::FAILURE;
        }

        $this->line("Status HTTP: {$response->status()}");
        $this->line('Waktu: '.$this->elapsed($started));

        $ok = $response->successful() ? $this->showResult($response) : $this->showError($response);

        if ($this->option('dump-structure')) {
            $this->dumpStructure($response);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function showConfig(LangflowSessionAnalyzer $analyzer): void
    {
        $key = (string) config('services.langflow.api_key');

        $this->line('LANGFLOW_URL      : '.(filled(config('services.langflow.url')) ? 'terisi' : 'kosong'));
        $this->line('LANGFLOW_FLOW_ID  : '.(filled(config('services.langflow.flow_id')) ? 'terisi' : 'kosong'));
        $this->line('LANGFLOW_API_KEY  : '.(filled($key) ? 'terisi, panjang '.strlen($key) : 'kosong'));
        $this->line("Timeout           : {$analyzer->timeout()} detik");
        $this->line('URL akhir         : '.($analyzer->isConfigured() ? $analyzer->endpoint() : '(belum lengkap)'));

        if (blank($key)) {
            $this->warn('LANGFLOW_API_KEY kosong: Langflow dengan autentikasi akan menolak dengan HTTP 401/403.');
        }
    }

    private function showResult(Response $response): bool
    {
        $text = LangflowSessionAnalyzer::extractText((array) $response->json());

        if ($text === null) {
            $this->error('Respons 2xx, tetapi tidak ada teks hasil yang bisa diekstrak. Jalankan dengan --dump-structure.');

            return false;
        }

        $this->line('Teks hasil:');
        $this->line(SecretRedactor::redact($text));

        try {
            $count = count(ErrorAnalysis::parse($text)['items']);
            $this->info("Teks berhasil dibaca sebagai analisis: {$count} item.");
        } catch (Throwable $e) {
            $this->warn('Teks bukan JSON analisis yang valid: '.$e->getMessage());
        }

        return true;
    }

    private function showError(Response $response): bool
    {
        $e = LangflowRequestException::fromResponse($response);
        $this->error($e->getMessage());
        $this->line($e->transient
            ? 'Jenis: sementara (job analisis akan mencoba lagi).'
            : 'Jenis: permanen (job analisis langsung memakai Gemini).');

        return false;
    }

    private function dumpStructure(Response $response): void
    {
        $data = $response->json();

        if (! is_array($data)) {
            $this->warn('Respons bukan JSON; tidak ada struktur untuk dicetak. Awal isi respons: '.SecretRedactor::snippet($response->body(), 200));

            return;
        }

        $this->line('');
        $this->line('Struktur respons (string dipotong '.self::DUMP_STRING_LIMIT.' karakter, sudah disamarkan):');
        foreach ($this->tree($data) as $line) {
            $this->line($line);
        }

        $path = storage_path(self::DUMP_FILE);
        file_put_contents($path, json_encode(self::redactDeep($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        $this->info("Respons lengkap yang sudah disamarkan disimpan ke {$path}");
        $this->line('Periksa isinya dulu, lalu salin ke tests/Fixtures/langflow_response.json.');
    }

    /** @return array<int, string> one line per key: "  key: value" for scalars, "  key:" before nested arrays */
    private function tree(array $node, int $depth = 0): array
    {
        $lines = [];
        $indent = str_repeat('  ', $depth);

        foreach ($node as $key => $value) {
            $label = is_int($key) ? "[{$key}]" : SecretRedactor::redact((string) $key);

            if (is_array($value)) {
                $lines[] = $indent.$label.':'.($value === [] ? ' (kosong)' : '');
                array_push($lines, ...$this->tree($value, $depth + 1));
            } elseif (is_string($value)) {
                $short = SecretRedactor::snippet($value, self::DUMP_STRING_LIMIT);
                $more = mb_strlen($value) > self::DUMP_STRING_LIMIT ? '…' : '';
                $lines[] = $indent.$label.': "'.str_replace(["\r", "\n"], ['\r', '\n'], $short).$more.'"';
            } else {
                $lines[] = $indent.$label.': '.json_encode($value);
            }
        }

        return $lines;
    }

    private static function redactDeep(array $node): array
    {
        $redacted = [];

        foreach ($node as $key => $value) {
            $key = is_string($key) ? SecretRedactor::redact($key) : $key;
            $redacted[$key] = match (true) {
                is_array($value) => self::redactDeep($value),
                is_string($value) => SecretRedactor::redact($value),
                default => $value,
            };
        }

        return $redacted;
    }

    private function elapsed(float $started): string
    {
        return number_format(microtime(true) - $started, 2).' detik';
    }
}
