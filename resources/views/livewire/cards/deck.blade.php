<div class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-4">Kartu</h1>

    {{-- Deck switcher --}}
    <div class="flex gap-2 mb-4" role="group" aria-label="Pilih deck">
        @foreach (['mandarin' => 'Mandarin', 'verb' => 'Verb', 'vocab' => 'Vocab IELTS'] as $key => $label)
            <button type="button" wire:click="switchDeck('{{ $key }}')" aria-pressed="{{ $deck === $key ? 'true' : 'false' }}"
                    class="flex-1 py-2 rounded-xl text-sm font-700 transition
                           {{ $deck === $key ? 'bg-navy text-white' : 'bg-card text-ink-soft hover:bg-card-alt' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($deck === 'mandarin')
        <div class="flex gap-2 mb-4 flex-wrap" role="group" aria-label="Level HSK">
            @foreach (['all' => 'Semua', '1' => 'HSK1', '2' => 'HSK2', '3' => 'HSK3', '4' => 'HSK4', '5' => 'HSK5', '6' => 'HSK6'] as $key => $label)
                @php $key = (string) $key; @endphp
                <button type="button" wire:click="setMandarinLevel('{{ $key }}')" aria-pressed="{{ $mandarinLevel === $key ? 'true' : 'false' }}"
                        class="px-3 py-1 rounded-full text-xs font-700 transition {{ $mandarinLevel === $key ? 'bg-orange text-navy' : 'bg-card text-ink-soft hover:bg-card-alt' }}">{{ $label }}</button>
            @endforeach
        </div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" wire:click="toggleHideKnown" @checked($hideKnown) class="rounded text-mandarin focus:ring-mandarin">
            Sembunyikan yang sudah hafal
        </label>
        <span class="text-xs text-ink-soft">{{ $this->cards->count() > 0 ? $index + 1 : 0 }} / {{ $this->cards->count() }}</span>
    </div>

    @if ($this->cards->isEmpty())
        <div class="bg-card rounded-2xl p-10 text-center text-ink-soft text-sm">
            @if ($hideKnown)
                Semua kartu di deck ini sudah kamu tandai hafal 🎉
            @else
                Belum ada kartu di deck ini. Coba pilih deck atau level lain.
            @endif
        </div>
    @else
        @php $card = $this->cards->get($index); @endphp

        <div class="flip-card {{ $flipped ? 'flipped' : '' }}" style="perspective: 1000px;">
            <div wire:click="flip" wire:keydown.enter.prevent="flip" wire:keydown.space.prevent="flip"
                 role="button" tabindex="0"
                 class="flip-card-inner relative cursor-pointer rounded-3xl" style="height: 260px;">
                {{-- Front --}}
                <div @if ($flipped) aria-hidden="true" @endif class="flip-card-front absolute inset-0 bg-card rounded-3xl shadow-sm flex flex-col items-center justify-center p-6 text-center">
                    @if ($deck === 'mandarin')
                        <p class="font-display text-5xl text-ink mb-2">{{ $card->hanzi }}</p>
                        <p class="text-ink-soft">{{ $card->pinyin }}</p>
                    @elseif ($deck === 'verb')
                        <p class="font-display text-3xl text-ink mb-1">{{ $card->base_form }}</p>
                        <p class="text-ink-faint text-sm">Past & past participle-nya apa?</p>
                    @else
                        <p class="font-display text-2xl text-ink mb-1">{{ $card->word_en }}</p>
                        <p class="text-ink-faint text-xs uppercase tracking-wide">{{ $card->topic }}</p>
                    @endif
                    <span class="absolute bottom-4 text-xs text-ink-soft">Ketuk atau tekan Enter untuk membalik</span>
                </div>
                {{-- Back --}}
                <div @if (! $flipped) aria-hidden="true" @endif class="flip-card-back absolute inset-0 bg-navy rounded-3xl shadow-sm flex flex-col items-center justify-center p-6 text-center text-white">
                    @if ($deck === 'mandarin')
                        @php
                            $primaryMeaning = \App\Support\MeaningDisplay::primary($card->meaning_id, $card->meaning_en);
                            $primaryIsEnglish = \App\Support\MeaningDisplay::primaryIsEnglish($card->meaning_id, $card->meaning_en);
                        @endphp
                        @if ($card->data_status === \App\Support\HskCorrections::STATUS)
                            <span class="absolute top-4 text-xs font-700 text-navy bg-orange rounded-full px-2 py-0.5">Data dikoreksi (draf)</span>
                        @endif
                        <p class="font-display text-2xl mb-1">{{ $card->hanzi }} · {{ $card->pinyin }}</p>
                        @if ($primaryMeaning !== null)
                            <p class="text-white text-lg">{{ $primaryMeaning }}</p>
                        @else
                            <p class="text-white/80 italic">Arti belum tersedia</p>
                        @endif
                        @if ($card->meaning_en && ! $primaryIsEnglish)
                            <p class="text-white/80 text-xs mt-1">{{ $card->meaning_en }}</p>
                        @endif
                    @elseif ($deck === 'verb')
                        <p class="font-display text-xl mb-1">{{ $card->base_form }} → {{ $card->past_form }} → {{ $card->past_participle }}</p>
                        <p class="text-white/80">{{ $card->meaning_id }}</p>
                    @else
                        <p class="font-display text-xl mb-1">{{ $card->word_en }}</p>
                        <p class="text-white/80">{{ $card->meaning_id }}</p>
                        <p class="text-white/80 text-xs mt-2 italic">"{{ $card->example_sentence }}"</p>
                    @endif
                </div>
            </div>
        </div>

        <p role="status" class="sr-only">{{ $flipped ? 'Kartu dibalik, sisi belakang ditampilkan.' : '' }}</p>

        <div class="flex items-center justify-center gap-3 mt-4">
            @if ($deck === 'verb')
                <button type="button"
                        onclick="speakSequence({{ \Illuminate\Support\Js::from([$card->base_form, $card->past_form, $card->past_participle]) }}, 'en-US')"
                        aria-label="Dengarkan {{ $card->base_form }}, {{ $card->past_form }}, {{ $card->past_participle }}"
                        class="w-11 h-11 rounded-full bg-card hover:bg-card-alt flex items-center justify-center shadow-sm"><span aria-hidden="true">🔊</span></button>
            @else
                <button type="button"
                        onclick="speak({{ \Illuminate\Support\Js::from($deck === 'mandarin' ? $card->hanzi : $card->word_en) }}, '{{ $deck === 'mandarin' ? 'zh-CN' : 'en-US' }}')"
                        aria-label="Dengarkan {{ $deck === 'mandarin' ? $card->hanzi : $card->word_en }}"
                        class="w-11 h-11 rounded-full bg-card hover:bg-card-alt flex items-center justify-center shadow-sm"><span aria-hidden="true">🔊</span></button>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 mt-4">
            <button type="button" wire:click="markKnown(false)" class="py-3 rounded-xl bg-card-alt text-ink-soft font-700 text-sm hover:bg-line-card">Belum Hafal</button>
            <button type="button" wire:click="markKnown(true)" class="py-3 rounded-xl bg-english-soft text-english font-700 text-sm hover:bg-english/20">Sudah Hafal <span aria-hidden="true">✓</span></button>
        </div>

        <div class="flex items-center justify-between mt-3">
            <button type="button" wire:click="prev" class="text-sm text-ink-soft font-700 px-3 py-2"><span aria-hidden="true">←</span> Sebelumnya</button>
            <button type="button" wire:click="next" class="text-sm text-ink-soft font-700 px-3 py-2">Berikutnya <span aria-hidden="true">→</span></button>
        </div>
    @endif
</div>
