<div class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <div class="flex items-center justify-between mb-4">
        <h1 class="font-display font-600 text-2xl text-ink">{{ ['today' => 'Tes Hari Ini', 'mandarin' => 'Ujian Mandarin', 'english' => 'Ujian English'][$mode] ?? 'Ujian' }}</h1>
        <a href="{{ route('ujian') }}" class="text-sm text-ink-soft"><span aria-hidden="true">←</span> Kembali</a>
    </div>

    @if ($this->previousTodayScore && ! $submitted)
        <div class="bg-card-alt rounded-xl px-4 py-2.5 text-sm text-ink-soft mb-4">
            Skor terakhir untuk hari ini: <span class="font-700 text-ink">{{ $this->previousTodayScore->correct }}/{{ $this->previousTodayScore->total }}</span>
        </div>
    @endif

    @if (empty($questions))
        <div class="bg-card rounded-2xl p-10 text-center text-ink-soft text-sm">
            Tidak ada vocab untuk hari ini. Coba mode Mandarin atau English.
        </div>
    @else
        <div role="status">
        @if ($submitted)
            <div class="bg-navy text-white rounded-2xl p-6 text-center mb-6">
                <h2 class="sr-only">Skor</h2>
                <p class="font-display text-3xl font-600">{{ $correctCount }} / {{ count($questions) }}</p>
                <p class="text-white/80 text-sm mt-1">{{ round($correctCount / max(1, count($questions)) * 100) }}% benar</p>
                <button type="button" wire:click="retry" class="mt-4 px-5 py-2 rounded-xl bg-white text-navy font-700 text-sm">Ulangi</button>
            </div>
        @endif
        </div>

        <div class="space-y-4">
            @foreach ($questions as $qi => $question)
                @php
                    $selected = $userAnswers[$qi] ?? null;
                    $correctIndex = $this->answerKey[$qi] ?? null;
                @endphp
                <div class="bg-card rounded-2xl p-4">
                    <p class="font-700 text-sm text-ink mb-3">{{ $qi + 1 }}. {{ $question['prompt'] }}</p>
                    <div class="space-y-2">
                        @foreach ($question['options'] as $oi => $option)
                            @php
                                $isSelected = $selected === $oi;
                                $isCorrectOption = $submitted && $correctIndex === $oi;
                                $isWrongSelected = $submitted && $isSelected && $correctIndex !== $oi;
                            @endphp
                            <button type="button"
                                    @if (!$submitted) wire:click="selectAnswer({{ $qi }}, {{ $oi }})" @endif
                                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                    class="w-full text-left px-4 py-2.5 rounded-xl text-sm border transition
                                           {{ $isCorrectOption ? 'border-good bg-good/10 text-ink' : ($isWrongSelected ? 'border-red-400 bg-red-50 text-ink' : ($isSelected ? 'border-mandarin bg-mandarin-soft text-ink' : 'border-line-card text-ink-soft hover:bg-card-alt')) }}">
                                {{ $option }}
                                @if ($isCorrectOption) <span class="float-right"><span aria-hidden="true">✓</span><span class="sr-only">(jawaban benar)</span></span> @endif
                                @if ($isWrongSelected) <span class="float-right"><span aria-hidden="true">✕</span><span class="sr-only">(jawabanmu salah)</span></span> @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if (! $submitted)
            <button type="button" wire:click="submit" class="w-full mt-6 py-3.5 rounded-xl bg-navy text-white font-700 hover:bg-navy-soft transition">
                Kumpulkan Jawaban
            </button>
        @endif
    @endif
</div>
