<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Lists the Gemini models this key can call with generateContent, to pick
 * GEMINI_MODEL or GEMINI_FALLBACK_MODELS without guessing. Only model names
 * are printed: never the key, and never any other part of a response. Listing
 * models does not spend generation quota.
 */
class GeminiModels extends Command
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** Guard against a pageToken that never ends. */
    private const MAX_PAGES = 20;

    protected $signature = 'gemini:models';

    protected $description = 'List Gemini models this key can use for generateContent (names only)';

    public function handle(): int
    {
        $key = (string) config('services.gemini.key');

        if ($key === '') {
            $this->error('GEMINI_API_KEY belum diisi; daftar model tidak bisa diambil.');

            return self::FAILURE;
        }

        $names = [];
        $pageToken = null;

        try {
            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $data = Http::timeout(15)
                    ->withHeaders(['x-goog-api-key' => $key])
                    ->get(self::URL, array_filter(['pageSize' => 1000, 'pageToken' => $pageToken]))
                    ->throw()
                    ->json();

                foreach ((array) ($data['models'] ?? []) as $model) {
                    if (in_array('generateContent', (array) ($model['supportedGenerationMethods'] ?? []), true) && is_string($model['name'] ?? null)) {
                        $names[] = preg_replace('#^models/#', '', $model['name']);
                    }
                }

                $pageToken = $data['nextPageToken'] ?? null;

                if (! is_string($pageToken) || $pageToken === '') {
                    break;
                }
            }
        } catch (RequestException $e) {
            $this->error($this->statusMessage($e->response->status()));

            return self::FAILURE;
        } catch (ConnectionException) {
            $this->error('Tidak bisa terhubung ke Gemini. Periksa koneksi internet lalu coba lagi.');

            return self::FAILURE;
        }

        if ($names === []) {
            $this->warn('Tidak ada model yang mendukung generateContent untuk kunci ini.');

            return self::SUCCESS;
        }

        sort($names);

        foreach (array_unique($names) as $name) {
            $this->line($name);
        }

        return self::SUCCESS;
    }

    private function statusMessage(int $status): string
    {
        return match (true) {
            in_array($status, [400, 401, 403], true) => "Kunci Gemini ditolak (HTTP {$status}). Periksa GEMINI_API_KEY.",
            $status === 429 => 'Kuota Gemini habis atau terlalu banyak permintaan (HTTP 429). Coba lagi nanti.',
            $status >= 500 => "Layanan Gemini sedang bermasalah (HTTP {$status}). Coba lagi sebentar.",
            default => "Daftar model gagal diambil (HTTP {$status}).",
        };
    }
}
