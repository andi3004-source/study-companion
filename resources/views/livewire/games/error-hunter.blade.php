<div class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-1">Pemburu Kesalahan</h1>
    <p class="text-ink-soft text-sm mb-6">Temukan jenis kesalahan di kalimat Mandarin ini.</p>

    @if ($questions === [])
        <div class="bg-card rounded-2xl p-10 text-center text-ink-soft text-sm">Data game belum tersedia.</div>
    @elseif ($finished)
        {{-- End screen --}}
        <div class="bg-card rounded-2xl p-6 text-center">
            <p class="text-ink-soft text-sm">Skor kamu</p>
            <p class="font-display font-600 text-4xl text-ink my-1">{{ $score }}/{{ count($questions) }}</p>
            <p class="text-ink-soft text-sm">Streak terbaik: {{ $bestStreak }} berturut-turut</p>

            @if ($this->weakest)
                <div class="mt-5 pt-5 border-t border-line-card text-left">
                    <p class="text-xs font-700 text-ink-soft mb-1">Paling sering salah</p>
                    <p class="font-700 text-ink">{{ $this->weakest['label'] }} <span class="text-ink-soft font-400">({{ $this->weakest['jumlah'] }}×)</span></p>
                    <p class="text-sm mt-1">
                        <span class="line-through text-ink-faint">{{ $this->weakest['salah'] }}</span>
                        → <span class="font-700 text-ink">{{ $this->weakest['benar'] }}</span>
                    </p>
                </div>
            @else
                <p class="mt-5 pt-5 border-t border-line-card text-sm text-ink">Semua jawaban benar. Mantap! 🎉</p>
            @endif

            <button type="button" wire:click="restart"
                    class="w-full mt-6 py-3 rounded-xl bg-navy text-white font-700 text-sm hover:bg-navy-soft">Main lagi</button>
        </div>
    @else
        @php $item = $this->current; $question = $questions[$index]; @endphp

        <div class="flex items-center justify-between text-xs text-ink-soft mb-3">
            <span>Soal {{ $index + 1 }}/{{ count($questions) }}</span>
            <span>Skor: {{ $score }} · Streak: {{ $streak }} 🔥</span>
        </div>

        <div class="bg-card rounded-2xl p-6 text-center mb-4">
            @if (\App\Support\ErrorGameItems::isDraft($item))
                <span class="inline-block mb-3 text-[10px] font-700 text-navy bg-orange/20 rounded-full px-2 py-0.5">Soal draf, sedang diverifikasi</span>
            @endif
            <p class="font-display text-4xl text-ink mb-2">{{ $item['salah'] }}</p>
            @if ($item['arti_id'])
                <p class="text-sm text-ink-soft">{{ $item['arti_id'] }}</p>
            @endif
        </div>

        <p class="text-sm font-700 text-ink mb-2">Kesalahannya termasuk jenis apa?</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
            @foreach ($question['options'] as $option)
                @php
                    $isCorrect = $option === $item['kategori'];
                    $isChosen = $option === $chosen;
                @endphp
                <button type="button" wire:click="answer('{{ $option }}')" @disabled($chosen !== null)
                        class="py-3 px-4 rounded-xl text-sm font-700 text-left border transition
                               {{ $chosen === null ? 'bg-card border-line-card text-ink hover:bg-card-alt' : ($isCorrect ? 'bg-english-soft border-english text-english' : ($isChosen ? 'bg-red-50 border-red-400 text-red-700' : 'bg-card border-line-card text-ink-faint')) }}">
                    {{-- Marks in text, not just color. --}}
                    @if ($chosen !== null && $isCorrect)
                        ✓
                    @elseif ($isChosen)
                        ✗
                    @endif
                    {{ \App\Enums\ErrorCategory::fromLoose($option)->label() }}
                </button>
            @endforeach
        </div>

        @if ($chosen !== null)
            <div class="bg-card rounded-2xl p-5 mb-4" role="status">
                @if ($chosen === $item['kategori'])
                    <p class="font-700 text-english">✓ Benar!</p>
                @else
                    <p class="font-700 text-red-700">✗ Salah. Jawabannya: {{ \App\Enums\ErrorCategory::fromLoose($item['kategori'])->label() }}</p>
                @endif

                <p class="text-xs text-ink-soft mt-3">Kalimat yang benar:</p>
                <p class="font-display text-2xl text-ink">{{ $item['benar'] }}</p>

                @if ($item['penjelasan'])
                    <p class="text-sm text-ink mt-2">{{ $item['penjelasan'] }}</p>
                @endif

                @if ($item['hanzi_di_luar_hsk1'] !== [])
                    <p class="text-xs text-ink-faint mt-2">Hanzi di luar HSK 1: {{ implode(', ', $item['hanzi_di_luar_hsk1']) }}</p>
                @endif
            </div>

            <button type="button" wire:click="next"
                    class="w-full py-3 rounded-xl bg-navy text-white font-700 text-sm hover:bg-navy-soft">
                {{ $index + 1 >= count($questions) ? 'Lihat hasil' : 'Berikutnya →' }}
            </button>
        @endif
    @endif
</div>
