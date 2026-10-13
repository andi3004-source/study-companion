{{--
    "Coba Langsung" example. There is no public demo in the app (practice needs an account and,
    for corrections, the AI), so this is a static example with sample data: no request is sent.
    The tabs and the speaker button work (welcome.js); everything else is shown, not interactive.
--}}
@php
    $contoh = [
        'mandarin' => ['tab' => 'Mandarin', 'jawaban' => '我很高兴', 'lang' => 'zh-Hans', 'hasil' => '我很高兴。', 'bacaan' => 'wǒ hěn gāoxìng', 'arti' => 'Saya senang.', 'ucap' => 'zh-CN'],
        'inggris' => ['tab' => 'Inggris', 'jawaban' => 'I am happy', 'lang' => 'en', 'hasil' => 'I am happy.', 'bacaan' => null, 'arti' => 'Saya senang.', 'ucap' => 'en-US'],
    ];
@endphp
<div class="relative">
    <section aria-labelledby="judul-contoh" class="rounded-card border border-brand-line bg-white p-5 shadow-card" data-kartu>
        <h3 id="judul-contoh" class="sr-only">Contoh tampilan latihan</h3>

        <div data-js-only hidden>
            <div role="tablist" aria-label="Bahasa contoh" class="flex gap-2">
                @foreach ($contoh as $key => $c)
                    <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}"
                            class="min-h-[44px] px-4 rounded-full text-sm font-bold text-ink-soft aria-selected:bg-brand-navy aria-selected:text-white">{{ $c['tab'] }}</button>
                @endforeach
            </div>
        </div>

        @foreach ($contoh as $key => $c)
            <div id="panel-{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}" @unless ($loop->first) hidden @endunless class="mt-4">
                <p class="text-sm font-bold text-ink-soft">Jawabanmu <span class="font-normal">(contoh)</span></p>
                <div class="mt-1 flex items-center gap-3 rounded-xl border-2 border-brand-line px-4 py-3" data-kartu>
                    <span lang="{{ $c['lang'] }}" class="flex-1 text-xl text-ink">{{ $c['jawaban'] }}</span>
                    {{-- Shows where the microphone button sits in the app; not a control here. --}}
                    <span class="w-11 h-11 rounded-xl bg-brand-navy text-white flex items-center justify-center" aria-hidden="true">
                        <x-landing.icon name="mic" class="w-5 h-5" />
                    </span>
                </div>

                <div class="mt-3 flex items-center gap-3 rounded-xl border border-[#BFE3CB] bg-[#EAF7EE] px-4 py-3">
                    <x-landing.icon name="check-circle" class="w-8 h-8 text-[#1D6436]" />
                    <div class="flex-1">
                        <p class="text-sm font-bold text-[#1D6436]"><span class="sr-only">Contoh hasil: </span>Jawaban sudah benar!</p>
                        <p class="text-ink">
                            <span lang="{{ $c['lang'] }}">{{ $c['hasil'] }}</span>
                            @if ($c['bacaan'])
                                <span class="text-ink-soft">({{ $c['bacaan'] }})</span>
                            @endif
                            <span class="block text-sm text-ink-soft">Arti: {{ $c['arti'] }}</span>
                        </p>
                    </div>
                    <span data-js-only="speech" hidden>
                        <button type="button" data-ucapkan="{{ $c['hasil'] }}" data-lang="{{ $c['ucap'] }}" aria-label="Dengarkan kalimat contoh {{ $c['tab'] }}"
                                class="w-11 h-11 rounded-full bg-white text-brand-navy flex items-center justify-center border border-brand-line">
                            <x-landing.icon name="speaker" class="w-5 h-5" />
                        </button>
                    </span>
                </div>
            </div>
        @endforeach
    </section>

    <p class="catatan-tangan hidden xl:block absolute -right-40 top-10 w-36 text-2xl text-center" aria-hidden="true">
        Coba sekali, langsung paham!
        <svg class="mx-auto mt-1 w-12 h-10" viewBox="0 0 48 40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M40 4c2 14-8 26-30 28M10 32l7-7M10 32l9 3"/></svg>
    </p>
</div>
