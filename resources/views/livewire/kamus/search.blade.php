<div data-voice-page="kamus" class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8" x-data="{ practiceChar: null }">
    <h1 class="font-display font-600 text-2xl text-ink mb-4">Kamus</h1>

    <div class="mb-3">
        <label for="cari-kamus" class="block text-sm font-700 text-ink mb-1">Cari kata</label>
        <input type="text" id="cari-kamus" wire:model.live.debounce.300ms="query"
               placeholder="Cari kata (Mandarin / pinyin / English / Indonesia)..."
               class="w-full rounded-xl border-line-card focus:border-mandarin focus:ring-mandarin text-sm">
    </div>

    <div class="flex gap-2 mb-3" role="group" aria-label="Bahasa">
        @foreach (['all' => 'Semua', 'mandarin' => 'Mandarin', 'english' => 'English'] as $key => $label)
            <button type="button" wire:click="$set('filterLang', '{{ $key }}')" aria-pressed="{{ $filterLang === $key ? 'true' : 'false' }}"
                    class="px-3 py-1.5 rounded-full text-xs font-700 transition {{ $filterLang === $key ? 'bg-navy text-white' : 'bg-card text-ink-soft hover:bg-card-alt' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($filterLang !== 'all')
        <div class="flex gap-2 mb-4 flex-wrap" role="group" aria-label="Level">
            <button type="button" wire:click="$set('filterLevel', 'all')" aria-pressed="{{ $filterLevel === 'all' ? 'true' : 'false' }}"
                    class="px-3 py-1 rounded-full text-xs font-700 transition {{ $filterLevel === 'all' ? 'bg-orange text-navy' : 'bg-card text-ink-soft hover:bg-card-alt' }}">Semua</button>
            @if ($filterLang === 'mandarin')
                @foreach ($mandarinLevels as $lvl)
                    <button type="button" wire:click="$set('filterLevel', '{{ $lvl }}')" aria-pressed="{{ $filterLevel === (string) $lvl ? 'true' : 'false' }}"
                            class="px-3 py-1 rounded-full text-xs font-700 transition {{ $filterLevel === $lvl ? 'bg-orange text-navy' : 'bg-card text-ink-soft hover:bg-card-alt' }}">HSK{{ $lvl }}</button>
                @endforeach
            @else
                @foreach ($englishLevels as $lvl)
                    <button type="button" wire:click="$set('filterLevel', '{{ $lvl }}')" aria-pressed="{{ $filterLevel === (string) $lvl ? 'true' : 'false' }}"
                            class="px-3 py-1 rounded-full text-xs font-700 transition {{ $filterLevel === $lvl ? 'bg-orange text-navy' : 'bg-card text-ink-soft hover:bg-card-alt' }}">{{ $lvl }}</button>
                @endforeach
            @endif
        </div>
    @endif

    <p role="status" class="text-xs text-ink-soft mb-2">
        <span wire:loading wire:target="query,filterLang,filterLevel">Mencari…</span>
        <span wire:loading.remove wire:target="query,filterLang,filterLevel">{{ number_format($this->totalCount) }} hasil</span>
    </p>

    <div class="space-y-2">
        @forelse ($this->results as $entry)
            <div data-hasil data-lang-code="{{ $entry->lang_code }}" class="bg-card rounded-xl px-4 py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p data-kata class="{{ $entry->lang === 'mandarin' ? 'font-display text-lg' : 'font-700 text-sm' }}">{{ $entry->headword }}</p>
                        @if ($entry->raw_level)
                            <span class="text-[10px] font-700 text-ink-faint bg-card-alt rounded-full px-1.5 py-0.5">
                                {{ $entry->lang === 'mandarin' ? 'HSK'.$entry->raw_level : $entry->raw_level }}
                            </span>
                        @elseif ($entry->source === 'verb')
                            <span class="text-[10px] font-700 text-ink-faint bg-card-alt rounded-full px-1.5 py-0.5">Kata Kerja</span>
                        @endif
                        @if ($entry->data_status === \App\Support\HskCorrections::STATUS)
                            <span class="text-[10px] font-700 text-navy bg-orange/20 rounded-full px-1.5 py-0.5">Data dikoreksi (draf)</span>
                        @endif
                    </div>
                    @if ($entry->sub)
                        <p data-bacaan class="text-xs text-ink-soft">{{ $entry->sub }}</p>
                    @endif
                    @php
                        $primaryMeaning = \App\Support\MeaningDisplay::primary($entry->meaning_id, $entry->meaning_en);
                        $primaryIsEnglish = \App\Support\MeaningDisplay::primaryIsEnglish($entry->meaning_id, $entry->meaning_en);
                    @endphp
                    @if ($primaryMeaning !== null)
                        <p class="text-sm text-ink">{{ $primaryIsEnglish ? '🇬🇧' : '🇮🇩' }} {{ $primaryMeaning }}</p>
                    @else
                        <p class="text-sm text-ink-faint italic">Arti belum tersedia</p>
                    @endif
                    @if ($entry->meaning_en && ! $primaryIsEnglish)
                        <p class="text-xs text-ink-faint">🇬🇧 {{ $entry->meaning_en }}</p>
                    @endif
                    @if ($entry->example_en)
                        <p class="text-xs text-ink-faint italic mt-0.5">"{{ $entry->example_en }}"</p>
                    @endif
                    @if ($entry->is_verb)
                        <div class="mt-1">
                            @if ($entry->verb_past && $entry->verb_past_participle)
                                <p class="text-xs font-700 text-navy">
                                    {{ $entry->headword }} <span class="text-ink-faint font-400">→</span> {{ $entry->verb_past }} <span class="text-ink-faint font-400">→</span> {{ $entry->verb_past_participle }}
                                </p>
                            @else
                                <span class="inline-block text-[10px] font-700 text-ink-faint bg-card-alt rounded-full px-1.5 py-0.5">Kata kerja beraturan (+ed)</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" onclick="speak({{ \Illuminate\Support\Js::from($entry->headword) }}, '{{ $entry->lang_code }}')"
                            aria-label="Dengarkan {{ $entry->headword }}"
                            class="w-9 h-9 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy"><span aria-hidden="true">🔊</span></button>
                    @if ($entry->lang === 'mandarin')
                        <button type="button" x-on:click="practiceChar = {{ \Illuminate\Support\Js::from($entry->headword) }}"
                                aria-label="Latihan tulis {{ $entry->headword }}"
                                class="w-9 h-9 rounded-full bg-mandarin-soft hover:bg-mandarin/20 flex items-center justify-center text-mandarin text-sm"><span aria-hidden="true">✍️</span></button>
                    @endif
                </div>
            </div>
        @empty
            <div data-kosong class="text-center text-ink-soft text-sm py-10">Kata tidak ditemukan. Coba ejaan lain, pinyin tanpa nada, atau ubah filter bahasa.</div>
        @endforelse
    </div>

    @if ($this->results->count() < $this->totalCount)
        <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                class="w-full mt-4 py-2.5 rounded-xl bg-card-alt text-ink-soft font-700 text-sm hover:bg-line-card transition disabled:opacity-50">
            Tampilkan lebih
        </button>
    @endif

    {{-- Hanzi writing practice modal --}}
    <template x-if="practiceChar">
        <div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-4" x-on:click.self="practiceChar = null"
             x-data="modalDialog()" x-on:keydown.escape="practiceChar = null">
            <div role="dialog" aria-modal="true" aria-labelledby="judul-latihan-tulis" class="bg-card rounded-3xl p-5 w-full max-w-sm" x-data="hanziCanvas(practiceChar)" x-init="$nextTick(() => init())">
                <div class="flex items-center justify-between mb-3">
                    <h2 id="judul-latihan-tulis" class="font-display font-600 text-lg" x-text="'Latihan Tulis: ' + practiceChar"></h2>
                    <button type="button" x-on:click="practiceChar = null" aria-label="Tutup" data-autofocus class="text-ink-soft text-xl leading-none"><span aria-hidden="true">✕</span></button>
                </div>
                <canvas x-ref="canvas" role="img" aria-label="Kanvas latihan menulis hanzi dengan jari atau mouse"
                        x-on:pointerdown="startDraw($event)"
                        x-on:pointermove="draw($event)"
                        x-on:pointerup="endDraw"
                        x-on:pointerleave="endDraw"
                        class="w-full rounded-xl touch-none bg-white"></canvas>
                <button type="button" x-on:click="clear" class="w-full mt-3 py-2.5 rounded-xl bg-card-alt text-ink-soft font-700 text-sm hover:bg-line-card">Hapus & Ulangi</button>
            </div>
        </div>
    </template>
</div>
