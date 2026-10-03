<?php

namespace App\Livewire;

use App\Contracts\PronunciationChecker as PronunciationCheckerContract;
use App\Services\KamusSearchService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Manual pronunciation-check page: pick a language, pick the target
 * word/phrase FROM THE DICTIONARY (KamusSearchService — same data Kamus
 * search uses, never free-typed text: a free-text field let users type
 * Indonesian into the Mandarin box and get a meaningless result), record
 * yourself saying it, get {isCorrect, feedback} back from whichever
 * PronunciationChecker is bound in AppServiceProvider (ML by default,
 * degrading to the free STT-match checker if the ML microservice is
 * unreachable — see MlPronunciationChecker).
 */
#[Layout('layouts.study')]
class PronunciationCheck extends Component
{
    use WithFileUploads;

    public string $lang = 'english';

    public string $query = '';

    /** @var array{headword: string, sub: ?string, meaning_id: ?string, meaning_en: ?string, lang_code: string}|null */
    public ?array $selectedWord = null;

    public $audioFile;

    /** @var array{isCorrect: bool, feedback: string}|null */
    public ?array $result = null;

    /** What the browser's own speech recognizer heard — shown as supplementary info alongside $result, never as the only output. */
    public ?string $transcript = null;

    public ?string $errorMessage = null;

    /**
     * ?lang=&word= from the dashboard checklist's "Cek Pengucapan" button
     * (a speaking item's topic) prefill the search box instead of starting
     * empty — the user still has to pick a suggestion themselves (the topic
     * string itself, e.g. "Family & Hometown", won't be a literal dictionary
     * headword), but they don't start from a blank search.
     */
    public function mount(): void
    {
        $lang = request()->query('lang');
        if (in_array($lang, ['english', 'mandarin'], true)) {
            $this->lang = $lang;
        }

        $word = request()->query('word');
        if (is_string($word) && trim($word) !== '') {
            $this->query = trim($word);
        }
    }

    public function updatedLang(): void
    {
        $this->clearSelection();
    }

    #[Computed]
    public function suggestions()
    {
        if ($this->selectedWord !== null || trim($this->query) === '') {
            return collect();
        }

        return app(KamusSearchService::class)->results($this->lang, 'all', $this->query, 8);
    }

    public function selectWord(string $headword, ?string $sub, ?string $meaningId, ?string $meaningEn, string $langCode): void
    {
        $this->selectedWord = [
            'headword' => $headword,
            'sub' => $sub,
            'meaning_id' => $meaningId,
            'meaning_en' => $meaningEn,
            'lang_code' => $langCode,
        ];
        $this->query = '';
        $this->result = null;
        $this->transcript = null;
        $this->errorMessage = null;
    }

    public function clearSelection(): void
    {
        $this->selectedWord = null;
        $this->query = '';
        $this->result = null;
        $this->transcript = null;
        $this->errorMessage = null;
    }

    public function checkPronunciation(?string $sttTranscript = null): void
    {
        $this->errorMessage = null;
        $this->result = null;
        $this->transcript = $sttTranscript;

        if (! $this->selectedWord) {
            $this->errorMessage = 'Pilih kata dari daftar dulu.';

            return;
        }

        $target = $this->selectedWord['headword'];

        // Defense in depth: the picker can only ever set $selectedWord from a
        // real dictionary row, so this should be unreachable in normal use —
        // but if it ever isn't, fail with a clear message instead of running
        // a Mandarin check against non-Hanzi text.
        if ($this->lang === 'mandarin' && ! preg_match('/\p{Han}/u', $target)) {
            $this->errorMessage = 'Kata target Mandarin harus mengandung karakter Hanzi.';

            return;
        }

        if (! $this->audioFile) {
            $this->errorMessage = 'Rekam suara dulu sebelum cek pengucapan.';

            return;
        }

        $targetPinyin = $this->lang === 'mandarin' ? $this->selectedWord['sub'] : null;

        try {
            $this->result = app(PronunciationCheckerContract::class)
                ->check($this->audioFile->getRealPath(), $target, $this->lang, $sttTranscript, $targetPinyin);
        } catch (Throwable $e) {
            report($e);
            $this->errorMessage = 'Terjadi kesalahan saat memverifikasi pengucapan. Coba lagi.';
        }

        $this->audioFile = null;
    }

    public function resetCheck(): void
    {
        $this->result = null;
        $this->transcript = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        return view('livewire.pronunciation-check');
    }
}
