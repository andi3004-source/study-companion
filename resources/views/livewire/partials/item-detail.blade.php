<div class="fixed inset-0 bg-black/40 z-50 flex items-end sidebar:items-center justify-center" wire:click.self="closeItem">
    <div class="bg-card w-full sidebar:max-w-lg sidebar:rounded-3xl rounded-t-3xl max-h-[85vh] overflow-y-auto">
        <div class="sticky top-0 bg-card flex items-start justify-between px-5 py-4 border-b border-line-card">
            <div>
                <span class="inline-block text-xs font-700 uppercase tracking-wide px-2 py-0.5 rounded-full mb-1
                             {{ $item['lang'] === 'mandarin' ? 'bg-mandarin-soft text-mandarin' : 'bg-english-soft text-english' }}">
                    {{ $item['lang'] === 'mandarin' ? 'Mandarin' : 'English' }}
                </span>
                <h2 class="font-display font-600 text-lg text-ink">{{ $item['title'] }}</h2>
            </div>
            <button wire:click="closeItem" class="text-ink-faint text-xl leading-none">✕</button>
        </div>

        <div class="p-5 space-y-4">
            @switch ($item['type'])
                @case ('vocab')
                    @foreach ($item['payload']['words'] as $word)
                        <div class="flex items-center justify-between bg-card-alt rounded-xl px-4 py-3">
                            <div>
                                @if (isset($word['hanzi']))
                                    <p class="font-display text-xl text-ink">{{ $word['hanzi'] }} <span class="text-sm font-sans text-ink-soft">{{ $word['pinyin'] }}</span></p>
                                    <p class="text-sm text-ink-soft">{{ $word['meaning_id'] }}</p>
                                @else
                                    <p class="font-700 text-ink">{{ $word['word_en'] }}</p>
                                    <p class="text-sm text-ink-soft">{{ $word['meaning_id'] }}</p>
                                    <p class="text-xs text-ink-faint italic mt-0.5">{{ $word['example_sentence'] }}</p>
                                @endif
                            </div>
                            <button type="button"
                                    onclick="speak('{{ addslashes($word['hanzi'] ?? $word['word_en']) }}', '{{ isset($word['hanzi']) ? 'zh-CN' : 'en-US' }}')"
                                    class="shrink-0 w-9 h-9 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy">
                                🔊
                            </button>
                        </div>
                    @endforeach

                    {{-- Primary internal action: practice this exact set of words in Kartu, not the whole 4991-word pool. --}}
                    @if ($item['lang'] === 'mandarin' && isset($item['payload']['batch_number']))
                        <a href="{{ route('cards.index', ['deck' => 'mandarin', 'batch' => $item['payload']['batch_number']]) }}"
                           class="block text-center rounded-xl bg-navy text-white font-700 py-3 no-underline">
                            Latihan Kartu Kata Ini
                        </a>
                    @elseif ($item['lang'] === 'english' && !empty($item['payload']['topic']))
                        <a href="{{ route('cards.index', ['deck' => 'vocab', 'topic' => $item['payload']['topic']]) }}"
                           class="block text-center rounded-xl bg-navy text-white font-700 py-3 no-underline">
                            Latihan Kartu Kata Ini
                        </a>
                    @endif
                    @break

                @case ('grammar')
                    @if ($item['lang'] === 'mandarin')
                        <div class="bg-mandarin-soft rounded-xl p-4">
                            <p class="font-700 text-sm text-mandarin mb-1.5">Pola Grammar</p>
                            <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['grammar'] }}</p>
                        </div>
                        @if ($item['payload']['listening'])
                            <div class="bg-card-alt rounded-xl p-4">
                                <p class="font-700 text-sm text-ink-soft mb-1.5">Listening</p>
                                <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['listening'] }}</p>
                            </div>
                        @endif
                    @else
                        <div class="bg-english-soft rounded-xl p-4">
                            <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['detail'] }}</p>
                        </div>
                        @if (!empty($item['payload']['verbs']))
                            <div class="space-y-2">
                                @foreach ($item['payload']['verbs'] as $v)
                                    <div class="bg-card-alt rounded-xl px-4 py-2.5 text-sm">
                                        <span class="font-700">{{ $v['base_form'] }}</span>
                                        <span class="text-ink-faint mx-1">→</span>
                                        <span class="font-700">{{ $v['past_form'] }}</span>
                                        <span class="text-ink-faint mx-1">→</span>
                                        <span class="font-700">{{ $v['past_participle'] }}</span>
                                        <span class="block text-xs text-ink-soft mt-0.5">{{ $v['meaning_id'] }} — "{{ $v['example_sentence'] }}"</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    {{--
                        No "Latihan Soal Terkait" button here: Ujian only has
                        3 whole-language modes (today/mandarin/english, see
                        Ujian\Runner) and Isian only drills Mandarin
                        hanzi/pinyin/meaning recall (LatihanIsian) — neither
                        has anything scoped to a specific grammar point
                        (Simple Past, Passive Voice, ...), so there's nothing
                        to genuinely match. Per spec: skip rather than force
                        a link to an unrelated whole-pool exam.
                    --}}
                    @break

                @case ('conversation')
                    @if (!empty($item['payload']['grammar']))
                        <div class="bg-mandarin-soft rounded-xl p-4 text-sm text-ink">{{ $item['payload']['grammar'] }}</div>
                    @endif
                    @foreach ($item['payload']['pairs'] as $pair)
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2 bg-card-alt rounded-xl px-4 py-3">
                                <div>
                                    <p class="font-display text-lg text-ink">{{ $pair['q_hanzi'] }}</p>
                                    <p class="text-xs text-ink-soft">{{ $pair['q_pinyin'] }}</p>
                                    <p class="text-xs text-ink-faint">{{ $pair['q_id'] }}</p>
                                </div>
                                <button type="button" onclick="speak('{{ addslashes($pair['q_hanzi']) }}', 'zh-CN')"
                                        class="shrink-0 w-8 h-8 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy text-sm">🔊</button>
                            </div>
                            <div class="flex items-start justify-between gap-2 bg-mandarin-soft rounded-xl px-4 py-3 ml-4">
                                <div>
                                    <p class="font-display text-lg text-ink">{{ $pair['a_hanzi'] }}</p>
                                    <p class="text-xs text-ink-soft">{{ $pair['a_pinyin'] }}</p>
                                    <p class="text-xs text-ink-faint">{{ $pair['a_id'] }}</p>
                                </div>
                                <button type="button" onclick="speak('{{ addslashes($pair['a_hanzi']) }}', 'zh-CN')"
                                        class="shrink-0 w-8 h-8 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy text-sm">🔊</button>
                            </div>
                        </div>
                    @endforeach
                    <div x-data="stopwatch()" class="flex items-center gap-3 bg-card-alt rounded-xl px-4 py-3">
                        <span class="font-display text-xl tabular-nums" x-text="display"></span>
                        <button type="button" x-show="!running" x-on:click="start" class="text-sm font-700 text-navy">▶ Mulai</button>
                        <button type="button" x-show="running" x-on:click="stop" class="text-sm font-700 text-navy">⏸ Stop</button>
                        <button type="button" x-on:click="reset" class="text-sm text-ink-faint">Reset</button>
                    </div>
                    @break

                @case ('speaking')
                    <div class="space-y-2">
                        @foreach ($item['payload']['prompts'] as $prompt)
                            <div class="flex items-start justify-between gap-2 bg-card-alt rounded-xl px-4 py-3">
                                <p class="text-sm text-ink whitespace-pre-line">{{ $prompt }}</p>
                                <button type="button" onclick="speak('{{ addslashes($prompt) }}', 'en-US')"
                                        class="shrink-0 w-8 h-8 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy text-sm">🔊</button>
                            </div>
                        @endforeach
                    </div>
                    <div x-data="stopwatch()" class="flex items-center gap-3 bg-card-alt rounded-xl px-4 py-3">
                        <span class="font-display text-xl tabular-nums" x-text="display"></span>
                        <button type="button" x-show="!running" x-on:click="start" class="text-sm font-700 text-navy">▶ Mulai</button>
                        <button type="button" x-show="running" x-on:click="stop" class="text-sm font-700 text-navy">⏸ Stop</button>
                        <button type="button" x-on:click="reset" class="text-sm text-ink-faint">Reset</button>
                    </div>
                    @include('livewire.partials.note-box', ['item' => $item])

                    {{-- Two internal ways to actually practice speaking it: a live AI chat, or the pronunciation checker with today's topic prefilled. --}}
                    <div class="grid grid-cols-1 sidebar:grid-cols-2 gap-2">
                        <button type="button" wire:click="startConversationFromItem('{{ $item['id'] }}')"
                                class="rounded-xl bg-navy text-white font-700 py-3">
                            💬 Latihan Ngobrol
                        </button>
                        <a href="{{ route('pronunciation-check', ['lang' => 'english', 'word' => $item['payload']['topic'] ?? '']) }}"
                           class="block text-center rounded-xl bg-card-alt text-ink border border-line-card font-700 py-3 no-underline">
                            🎤 Cek Pengucapan
                        </a>
                    </div>
                    @break

                @case ('writing')
                    {{-- Already internal (the textarea below is this item's main action) — no change needed here. --}}
                    <div class="bg-card-alt rounded-xl p-4">
                        <p class="text-sm text-ink whitespace-pre-line">{{ $item['payload']['prompt'] }}</p>
                    </div>
                    @include('livewire.partials.note-box', ['item' => $item, 'showWordCount' => true])
                    @break

                @case ('listening')
                    <div class="bg-card-alt rounded-xl p-4">
                        <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['tip'] }}</p>
                    </div>

                    {{--
                        No real listening-comprehension feature exists yet
                        (see Scheduler::listeningItem()) — this plays the
                        day's vocab words back to back via TTS as a simple
                        in-app stand-in, instead of sending the user
                        straight to an external platform for something this
                        simple.
                    --}}
                    @if (!empty($item['payload']['words']))
                        <button type="button" onclick="speakSequence({{ \Illuminate\Support\Js::from($item['payload']['words']) }}, 'en-US')"
                                class="w-full rounded-xl bg-navy text-white font-700 py-3">
                            🔊 Dengar & Ulangi
                        </button>
                    @endif
                    @break

                @case ('reading')
                    <div class="bg-card-alt rounded-xl p-4">
                        <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['tip'] }}</p>
                    </div>
                    @break

                @case ('dictation')
                    <div class="bg-card-alt rounded-xl p-4">
                        <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['tip'] }}</p>
                    </div>
                    @if (!empty($item['payload']['listening_text']))
                        <div class="bg-mandarin-soft rounded-xl p-4 text-sm text-ink">{{ $item['payload']['listening_text'] }}</div>
                    @endif
                    @break

                @case ('review')
                @case ('quiz')
                    <div class="bg-card-alt rounded-xl p-4">
                        <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['tip'] }}</p>
                    </div>

                    {{-- Isian only drills Mandarin vocab, so this only applies there — English review has no internal match, external link only. --}}
                    @if ($item['lang'] === 'mandarin')
                        <a href="{{ route('latihan-isian', array_filter(['batchFrom' => $item['payload']['isian_batch_from'] ?? null, 'batchTo' => $item['payload']['isian_batch_to'] ?? null])) }}"
                           class="block text-center rounded-xl bg-navy text-white font-700 py-3 no-underline">
                            Mulai Latihan Isian
                        </a>
                    @endif
                    @break

                @case ('mock')
                    <div class="bg-card-alt rounded-xl p-4">
                        <p class="text-sm text-ink leading-relaxed">{{ $item['payload']['tip'] }}</p>
                    </div>
                    <a href="{{ route('ujian') }}" class="block text-center rounded-xl bg-navy text-white font-700 py-3 no-underline">Buka Halaman Ujian</a>
                    @break

                @case ('info')
                    @foreach ($item['payload']['details'] as $detail)
                        <div class="bg-card-alt rounded-xl p-4">
                            <p class="font-700 text-sm text-ink mb-1">{{ $detail['label'] }}</p>
                            <p class="text-sm text-ink-soft leading-relaxed">{{ $detail['text'] }}</p>
                        </div>
                    @endforeach

                    @if (!empty($item['payload']['tone_examples']))
                        <div class="bg-mandarin-soft rounded-xl p-4">
                            <p class="font-700 text-sm text-mandarin mb-2">Contoh 4 Nada</p>
                            <div class="flex flex-wrap gap-4">
                                @foreach ($item['payload']['tone_examples'] as $example)
                                    <span class="font-display text-xl text-ink">{{ $example['hanzi'] }} <span class="text-sm font-sans text-ink-soft">{{ $example['pinyin'] }}</span></span>
                                @endforeach
                            </div>
                        </div>
                        <button type="button"
                                onclick="speakSequence({{ \Illuminate\Support\Js::from(collect($item['payload']['tone_examples'])->pluck('hanzi')->all()) }}, 'zh-CN')"
                                class="w-full rounded-xl bg-navy text-white font-700 py-3">
                            🔊 Dengar Contoh Nada
                        </button>
                    @endif
                    @break
            @endswitch

            @if (!empty($item['links']))
                <div class="pt-3 mt-1 border-t border-line-card">
                    <div class="text-[10px] font-600 text-ink-faint mb-2 tracking-wide">Referensi Tambahan</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($item['links'] as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                               class="flex items-center gap-1 bg-card-alt text-ink-faint text-[11px] font-600 px-2.5 py-1 rounded-full no-underline hover:text-ink-soft hover:bg-line-card">
                                {{ $link['name'] }} ↗
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
