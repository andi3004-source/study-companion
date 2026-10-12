<div data-voice-page="game-error-hunter" class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <p class="mb-3"><a href="{{ route('game') }}" class="inline-flex items-center min-h-[44px] text-sm font-700 text-navy underline"><span aria-hidden="true">←</span>&nbsp;Semua game</a></p>
    <h1 class="font-display font-600 text-2xl text-ink mb-1">Pemburu Kesalahan</h1>
    <p class="text-ink-soft text-sm mb-6">Temukan jenis kesalahan di kalimat Mandarin ini.</p>

    @if ($questions === [])
        <div role="status" class="bg-card rounded-2xl p-6 text-ink">
            <p class="font-700">Data game belum tersedia.</p>
            <p class="mt-1">Berkas soal Pemburu Kesalahan belum dipasang, jadi belum ada soal untuk dimainkan. Coba game lain di halaman <a href="{{ route('game') }}" class="underline font-700 text-navy">Game</a>.</p>
        </div>
    @elseif ($finished)
        {{-- End screen --}}
        <section aria-labelledby="judul-hasil" class="bg-card rounded-2xl p-6 text-center">
            <h2 id="judul-hasil" tabindex="-1" x-data x-init="$el.focus()" class="font-display font-600 text-xl text-ink">Hasil</h2>
            <div role="status">
                <p class="text-ink-soft">Skor kamu</p>
                <p class="font-display font-600 text-4xl text-ink my-1">{{ $score }}/{{ count($questions) }}</p>
                <p class="text-ink-soft">Streak terbaik: {{ $bestStreak }} berturut-turut</p>
            </div>

            @if ($this->weakest)
                <div class="mt-5 pt-5 border-t border-line-card text-left">
                    <p class="text-sm font-700 text-ink-soft mb-1">Paling sering salah</p>
                    <p class="font-700 text-ink">{{ $this->weakest['label'] }} <span class="text-ink-soft font-400">({{ $this->weakest['jumlah'] }}×)</span></p>
                    <p class="mt-1">
                        <span class="sr-only">Salah:</span>
                        <span lang="zh-Hans" class="line-through text-ink-soft">{{ $this->weakest['salah'] }}</span>
                        <span aria-hidden="true">→</span>
                        <span class="sr-only">Benar:</span>
                        <span lang="zh-Hans" class="font-700 text-ink">{{ $this->weakest['benar'] }}</span>
                    </p>
                </div>
            @else
                <p class="mt-5 pt-5 border-t border-line-card text-ink">Semua jawaban benar. Mantap! <span aria-hidden="true">🎉</span></p>
            @endif

            <button type="button" wire:click="restart"
                    class="w-full mt-6 min-h-[3.5rem] rounded-xl bg-navy text-white font-700 hover:bg-navy-soft">Main lagi</button>
        </section>
    @else
        @php $item = $this->current; $question = $questions[$index]; @endphp

        <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-ink-soft mb-3">
            <span>Soal {{ $index + 1 }}/{{ count($questions) }}</span>
            <span>Skor: {{ $score }} · Streak: {{ $streak }} <span aria-hidden="true">🔥</span></span>
        </div>

        <section aria-labelledby="judul-soal" wire:key="soal-{{ $index }}" class="bg-card rounded-2xl p-6 text-center mb-4 space-y-2">
            <h2 id="judul-soal" class="sr-only">Soal {{ $index + 1 }}</h2>
            @if (\App\Support\ErrorGameItems::isDraft($item))
                <span class="inline-block text-xs font-700 text-navy bg-orange/20 rounded-full px-2 py-0.5">Soal draf, sedang diverifikasi</span>
            @endif
            <p lang="zh-Hans" class="font-display text-[4rem] leading-tight text-ink">{{ $item['salah'] }}</p>
            @if ($item['arti_id'])
                <p class="text-ink-soft">{{ $item['arti_id'] }}</p>
            @endif
            <button type="button" x-data x-on:click="window.speak({{ \Illuminate\Support\Js::from($item['salah']) }}, 'zh-CN', null, window.gameSpeechRate().nilai())"
                    class="min-h-[3.5rem] px-5 rounded-xl border-2 border-navy text-navy font-700"><span aria-hidden="true">🔊</span> Dengarkan kalimat</button>
        </section>

        <form wire:submit="jawabPilihan" class="space-y-4 mb-4">
            <fieldset @disabled($chosen !== null)>
                <legend class="font-700 text-lg text-ink mb-3">Kesalahannya termasuk jenis apa?</legend>
                <div class="flex flex-col gap-3">
                    @foreach ($question['options'] as $i => $option)
                        @php
                            $isCorrect = $option === $item['kategori'];
                            $isChosen = $option === $chosen;
                        @endphp
                        <label for="kategori-{{ $index }}-{{ $i }}"
                               class="flex flex-wrap items-center gap-3 min-h-[3.5rem] w-full px-4 py-3 rounded-xl border-2 text-lg text-ink bg-card
                                      {{ $chosen !== null && $isCorrect ? 'border-english' : ($isChosen ? 'border-red-700' : 'border-line-card') }}">
                            <input type="radio" id="kategori-{{ $index }}-{{ $i }}" name="kategori-{{ $index }}" value="{{ $option }}" wire:model="pilihanKategori" class="w-6 h-6">
                            <span>{{ \App\Enums\ErrorCategory::fromLoose($option)->label() }}</span>
                            {{-- Marks in text, not just color. --}}
                            @if ($chosen !== null && $isCorrect)
                                <span class="ml-auto font-700 text-english"><span aria-hidden="true">✓</span> jawaban benar</span>
                            @elseif ($isChosen)
                                <span class="ml-auto font-700 text-red-700"><span aria-hidden="true">✗</span> pilihanmu</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('pilihanKategori')
                <p role="alert" class="text-red-700 font-700">{{ $message }}</p>
            @enderror
            @if ($chosen === null)
                <button type="submit" class="w-full min-h-[3.5rem] px-5 rounded-xl bg-navy text-white font-700 text-lg">Jawab</button>
            @endif
        </form>

        {{-- Always rendered so the feedback is announced when it appears. --}}
        <div role="status" class="mb-4">
            @if ($chosen !== null)
                <div class="bg-card rounded-2xl p-5 space-y-2">
                    @if ($chosen === $item['kategori'])
                        <p class="text-lg font-700 text-english">✓ Benar!</p>
                    @else
                        <p class="text-lg font-700 text-red-700">✗ Salah. Jawabannya: {{ \App\Enums\ErrorCategory::fromLoose($item['kategori'])->label() }}</p>
                    @endif

                    <p class="text-ink-soft">Kalimat yang benar:</p>
                    <p lang="zh-Hans" class="font-display text-[2.5rem] leading-tight text-ink">{{ $item['benar'] }}</p>

                    @if ($item['penjelasan'])
                        <p class="text-ink">{{ $item['penjelasan'] }}</p>
                    @endif

                    @if ($item['hanzi_di_luar_hsk1'] !== [])
                        <p class="text-sm text-ink-soft">Hanzi di luar HSK 1: {{ implode(', ', $item['hanzi_di_luar_hsk1']) }}</p>
                    @endif
                </div>
            @endif
        </div>

        @if ($chosen !== null)
            <div class="flex flex-col gap-3">
                <button type="button" x-data x-on:click="window.speak({{ \Illuminate\Support\Js::from($item['benar']) }}, 'zh-CN', null, window.gameSpeechRate().nilai())"
                        class="w-full min-h-[3.5rem] px-5 rounded-xl border-2 border-navy text-navy font-700"><span aria-hidden="true">🔊</span> Dengarkan kalimat yang benar</button>
                <button type="button" wire:click="next" x-data x-init="$el.focus()"
                        class="w-full min-h-[3.5rem] px-5 rounded-xl bg-navy text-white font-700 text-lg">
                    {{ $index + 1 >= count($questions) ? 'Lihat hasil' : 'Berikutnya →' }}
                </button>
            </div>
        @endif
    @endif
</div>
