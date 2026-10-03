<?php

namespace App\Livewire\Ujian;

use App\Models\UserExamScore;
use App\Services\ExamGenerator;
use App\Services\Scheduler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.study')]
class Runner extends Component
{
    public string $mode;

    public int $day;

    public array $questions = [];

    public array $userAnswers = [];

    public bool $submitted = false;

    public int $correctCount = 0;

    public string $examId;

    public function mount(string $mode): void
    {
        $this->mode = in_array($mode, ['today', 'mandarin', 'english'], true) ? $mode : 'mandarin';
        $this->day = (int) (request()->query('day') ?: (Auth::user()->dayNumber() ?? 1));
        $this->examId = (string) Str::uuid();
        $this->generateQuestions();
    }

    protected function generateQuestions(): void
    {
        $gen = app(ExamGenerator::class);

        $result = match ($this->mode) {
            'today' => $gen->today($this->day, app(Scheduler::class)),
            'english' => $gen->english(50),
            default => $gen->mandarin(50),
        };

        $this->questions = $result['questions'];
        $this->userAnswers = array_fill(0, count($this->questions), null);

        // Correct-answer key stays server-side only; never exposed as a public Livewire property.
        session(['exam_key_'.$this->examId => $result['answer_key']]);
    }

    #[Computed]
    public function previousTodayScore(): ?UserExamScore
    {
        if ($this->mode !== 'today') {
            return null;
        }

        return UserExamScore::where('user_id', Auth::id())
            ->where('day_number', $this->day)
            ->where('exam_mode', 'today')
            ->latest('taken_at')
            ->first();
    }

    public function selectAnswer(int $questionIndex, int $optionIndex): void
    {
        if ($this->submitted) {
            return;
        }

        $this->userAnswers[$questionIndex] = $optionIndex;
    }

    public function submit(): void
    {
        $answerKey = session('exam_key_'.$this->examId, []);
        $correct = 0;

        foreach ($answerKey as $i => $correctIndex) {
            if (($this->userAnswers[$i] ?? null) === $correctIndex) {
                $correct++;
            }
        }

        $this->correctCount = $correct;
        $this->submitted = true;

        UserExamScore::create([
            'user_id' => Auth::id(),
            'day_number' => $this->day,
            'exam_mode' => $this->mode,
            'correct' => $correct,
            'total' => count($answerKey),
            'taken_at' => now(),
        ]);
    }

    #[Computed]
    public function answerKey(): array
    {
        return $this->submitted ? session('exam_key_'.$this->examId, []) : [];
    }

    public function retry(): void
    {
        session()->forget('exam_key_'.$this->examId);
        $this->submitted = false;
        $this->correctCount = 0;
        $this->examId = (string) Str::uuid();
        $this->generateQuestions();
        unset($this->answerKey);
    }

    public function render()
    {
        return view('livewire.ujian.runner');
    }
}
