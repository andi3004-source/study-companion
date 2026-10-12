<div class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 space-y-4">
    <p><a href="{{ route('game') }}" class="inline-flex items-center min-h-[44px] text-sm font-700 text-navy underline"><span aria-hidden="true">←</span>&nbsp;Semua game</a></p>
    <h1 class="font-display font-600 text-2xl text-ink">Dengar &amp; Pilih</h1>
    <p class="text-ink">Dengarkan kata Mandarin, baca hanzi dan pinyinnya, lalu pilih artinya. Tidak ada batas waktu.</p>

    {{-- Speech speed, saved in this browser only. --}}
    <div x-data="gameSpeechRate()" class="flex flex-wrap items-center gap-2">
        <label for="kecepatan-suara" class="font-700 text-ink">Kecepatan suara</label>
        <select id="kecepatan-suara" x-model="kecepatan" x-on:change="simpan()" class="rounded-xl border-line-card text-ink">
            <option value="normal">Normal</option>
            <option value="lambat">Lambat</option>
        </select>
    </div>

    @if ($questions === [])
        <div role="status" class="bg-card rounded-2xl p-6 text-ink">
            <p class="font-700">Soal belum tersedia.</p>
            <p class="mt-1">Belum ada cukup kosakata HSK 1 yang punya arti bahasa Indonesia untuk membuat soal. Isi data kosakata dulu, lalu buka halaman ini lagi.</p>
        </div>
    @elseif ($finished)
        <section aria-labelledby="judul-hasil" class="bg-card rounded-2xl p-6 space-y-3">
            <h2 id="judul-hasil" tabindex="-1" x-data x-init="$el.focus()" class="font-display font-600 text-xl text-ink">Hasil</h2>
            <p role="status" class="text-lg text-ink">
                Skor kamu: {{ $score }} dari {{ count($questions) }}.
                @if ($missed === [])
                    Semua jawaban benar. <span aria-hidden="true">✓</span>
                @else
                    Ada {{ count($missed) }} kata yang meleset.
                @endif
            </p>
            @if ($missed !== [])
                <h3 class="font-700 text-ink">Kata yang meleset</h3>
                <ul class="space-y-2">
                    @foreach ($missed as $word)
                        <li class="text-ink"><span lang="zh-Hans" class="font-display text-2xl">{{ $word['hanzi'] }}</span> <span class="text-ink-soft">{{ $word['pinyin'] }}</span>: {{ $word['arti'] }}</li>
                    @endforeach
                </ul>
            @endif
            <button type="button" wire:click="restart" class="w-full min-h-[3.5rem] px-5 rounded-xl bg-navy text-white font-700">Main lagi</button>
        </section>
    @else
        @php $question = $questions[$index]; @endphp
        <p class="text-ink-soft">Soal {{ $index + 1 }} dari {{ count($questions) }} · Skor {{ $score }}</p>

        <section aria-labelledby="judul-soal" wire:key="soal-{{ $index }}" class="bg-card rounded-2xl p-6 text-center space-y-2"
                 x-data x-init="if (! window.screenReaderMode()) window.speak({{ \Illuminate\Support\Js::from($question['hanzi']) }}, 'zh-CN', null, window.gameSpeechRate().nilai())">
            <h2 id="judul-soal" class="sr-only">Soal {{ $index + 1 }}</h2>
            <p lang="zh-Hans" class="font-display text-[4rem] leading-tight text-ink">{{ $question['hanzi'] }}</p>
            <p class="text-xl text-ink"><span class="sr-only">Pinyin:</span> {{ $question['pinyin'] }}</p>
            <button type="button" x-on:click="window.speak({{ \Illuminate\Support\Js::from($question['hanzi']) }}, 'zh-CN', null, window.gameSpeechRate().nilai())"
                    class="min-h-[3.5rem] px-5 rounded-xl border-2 border-navy text-navy font-700"><span aria-hidden="true">🔊</span> Dengarkan lagi</button>
        </section>

        <form wire:submit="jawab" class="space-y-4">
            <fieldset @disabled($chosen !== null)>
                <legend class="font-700 text-lg text-ink mb-3">Apa arti kata ini?</legend>
                <div class="flex flex-col gap-3">
                    @foreach ($question['options'] as $i => $option)
                        @php
                            $isAnswer = $option === $question['arti'];
                            $isChosen = $chosen === $i;
                        @endphp
                        <label for="pilihan-{{ $index }}-{{ $i }}"
                               class="flex flex-wrap items-center gap-3 min-h-[3.5rem] w-full px-4 py-3 rounded-xl border-2 text-lg text-ink bg-card
                                      {{ $chosen !== null && $isAnswer ? 'border-english' : ($isChosen ? 'border-red-700' : 'border-line-card') }}">
                            <input type="radio" id="pilihan-{{ $index }}-{{ $i }}" name="pilihan-{{ $index }}" value="{{ $i }}" wire:model="pilihan" class="w-6 h-6">
                            <span>{{ $option }}</span>
                            @if ($chosen !== null && $isAnswer)
                                <span class="ml-auto font-700 text-english"><span aria-hidden="true">✓</span> jawaban benar</span>
                            @elseif ($isChosen)
                                <span class="ml-auto font-700 text-red-700"><span aria-hidden="true">✗</span> pilihanmu</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('pilihan')
                <p role="alert" class="text-red-700 font-700">{{ $message }}</p>
            @enderror
            @if ($chosen === null)
                <button type="submit" class="w-full min-h-[3.5rem] px-5 rounded-xl bg-navy text-white font-700 text-lg">Jawab</button>
            @endif
        </form>

        <div role="status" class="space-y-3">
            @if ($chosen !== null)
                <div class="bg-card rounded-2xl p-5 space-y-2">
                    @if ($question['options'][$chosen] === $question['arti'])
                        <p class="text-lg font-700 text-english"><span aria-hidden="true">✓</span> Benar. <span lang="zh-Hans">{{ $question['hanzi'] }}</span> artinya {{ $question['arti'] }}.</p>
                    @else
                        <p class="text-lg font-700 text-red-700"><span aria-hidden="true">✗</span> Salah, jawabannya {{ $question['arti'] }}.</p>
                    @endif
                </div>
            @endif
        </div>

        @if ($chosen !== null)
            <div class="flex flex-col gap-3">
                <button type="button" x-data x-on:click="window.speak({{ \Illuminate\Support\Js::from($question['hanzi']) }}, 'zh-CN', null, window.gameSpeechRate().nilai())"
                        class="w-full min-h-[3.5rem] px-5 rounded-xl border-2 border-navy text-navy font-700"><span aria-hidden="true">🔊</span> Dengarkan jawaban</button>
                <button type="button" wire:click="next" x-data x-init="$el.focus()"
                        class="w-full min-h-[3.5rem] px-5 rounded-xl bg-navy text-white font-700 text-lg">{{ $index + 1 >= count($questions) ? 'Lihat hasil' : 'Soal berikutnya' }}</button>
            </div>
        @endif
    @endif
</div>
