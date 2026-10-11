<div class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 space-y-4">
    <h1 class="font-display font-600 text-2xl text-ink">Kata bicara</h1>
    <p class="text-ink">Cari satu kata dan lihat dalam bahasa Indonesia, Inggris, dan Mandarin.</p>

    @if (! $available)
        <p class="bg-card rounded-2xl p-5 text-ink" role="status">Katalog belum tersedia</p>
    @else
        <form wire:submit="cari" class="bg-card rounded-2xl p-5 space-y-3">
            <label for="cari-kata" class="block text-sm font-700 text-ink">Cari kata</label>
            <p id="petunjuk-cari-kata" class="text-xs text-ink-soft">Ketik nama dalam bahasa Indonesia, Inggris, atau hanzi, lalu tekan Enter.</p>
            <div class="flex flex-wrap gap-2">
                <input type="text" id="cari-kata" wire:model="query" aria-describedby="petunjuk-cari-kata" autocomplete="off"
                       class="flex-1 min-w-0 rounded-xl border-line-card focus:border-mandarin focus:ring-mandarin text-sm">
                <button type="submit" class="px-5 rounded-xl bg-navy text-white font-700 text-sm hover:bg-navy-soft">Cari</button>
            </div>

            {{--
                Voice: Web Speech API (Chrome/Edge), id-ID. What was heard is shown
                and announced first; the search only runs after the user presses Ya.
            --}}
            <div x-data="wordListener()" class="space-y-2">
                <button type="button" x-show="supported" x-on:click="start()"
                        x-bind:aria-pressed="state === 'listening' ? 'true' : 'false'" aria-pressed="false"
                        class="px-4 rounded-xl border border-line-card bg-card-alt text-ink font-700 text-sm">
                    <span aria-hidden="true">🎤</span> Ucapkan kata
                </button>
                <p class="text-xs text-ink-soft" x-show="!supported">Ucapkan kata butuh Chrome atau Edge.</p>

                <div role="status" class="text-sm text-ink">
                    <span x-show="state === 'listening'">Mendengarkan…</span>
                    <span x-show="state === 'confirm'" x-text="'Saya dengar: ' + heard + '. Benar?'"></span>
                    <span x-show="message" x-text="message"></span>
                </div>
                <div x-show="state === 'confirm'" x-cloak class="flex gap-2">
                    <button type="button" x-ref="ya" x-on:click="confirm()" class="px-4 rounded-xl bg-navy text-white font-700 text-sm">Ya</button>
                    <button type="button" x-on:click="start()" class="px-4 rounded-xl border border-line-card text-ink font-700 text-sm">Cari ulang</button>
                </div>
            </div>
        </form>

        <div role="status" id="hasil-kata-bicara">
            @if ($searched !== null && $results->isEmpty())
                <div class="bg-card rounded-2xl p-5 text-ink">
                    <p class="font-700">Belum ada di daftar kami: <span>{{ $searched }}</span></p>
                    @if ($examples)
                        <p class="text-sm mt-2">Kata yang tersedia, misalnya:</p>
                        <ul class="list-disc pl-6 text-sm">
                            @foreach ($examples as $example)
                                <li lang="id">{{ $example }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @elseif ($results->isNotEmpty())
                <p class="sr-only">Ditemukan {{ $results->count() }} kata.</p>
            @endif
        </div>

        @foreach ($results as $entry)
            <article class="bg-card rounded-2xl p-5" aria-labelledby="kata-{{ $loop->index }}">
                <h2 id="kata-{{ $loop->index }}" class="font-display font-600 text-lg text-ink" lang="id">{{ $entry['indonesia'] }}</h2>
                <dl class="mt-3 space-y-3">
                    <div>
                        <dt class="text-xs font-700 text-ink-soft">Indonesia</dt>
                        <dd class="flex flex-wrap items-center gap-3">
                            <span lang="id" class="text-ink">{{ $entry['indonesia'] }}</span>
                            <button type="button" onclick="speak({{ \Illuminate\Support\Js::from($entry['indonesia']) }}, 'id-ID')"
                                    class="px-3 rounded-lg border border-line-card text-sm text-ink">Dengarkan Indonesia</button>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-700 text-ink-soft">Inggris</dt>
                        <dd class="flex flex-wrap items-center gap-3">
                            <span lang="en" class="text-ink">{{ $entry['inggris'] }}</span>
                            @if ($entry['ipa_inggris'] !== '')
                                <span class="text-sm text-ink-soft"><span class="sr-only">Pelafalan IPA:</span> {{ $entry['ipa_inggris'] }}</span>
                            @endif
                            <button type="button" onclick="speak({{ \Illuminate\Support\Js::from($entry['inggris']) }}, 'en-US')"
                                    class="px-3 rounded-lg border border-line-card text-sm text-ink">Dengarkan Inggris</button>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-700 text-ink-soft">Mandarin</dt>
                        <dd class="flex flex-wrap items-center gap-3">
                            <span lang="zh-Hans" class="text-ink text-xl">{{ $entry['hanzi'] }}</span>
                            <span class="text-sm text-ink-soft"><span class="sr-only">Pinyin:</span> {{ $entry['pinyin'] }}</span>
                            <button type="button" onclick="speak({{ \Illuminate\Support\Js::from($entry['hanzi']) }}, 'zh-CN')"
                                    class="px-3 rounded-lg border border-line-card text-sm text-ink">Dengarkan Mandarin</button>
                        </dd>
                    </div>
                </dl>
            </article>
        @endforeach
    @endif
</div>
