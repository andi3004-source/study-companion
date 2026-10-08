<div class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 flex flex-col" style="min-height: calc(100vh - 2rem);">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="font-display font-600 text-xl text-ink">{{ $this->scenario['label'] }}</h1>
            <p class="text-xs text-ink-soft">{{ ucfirst($session->lang) }} · Sisa kuota hari ini: {{ $this->remainingToday }}/{{ $dailyLimit }}</p>
            @if ($this->levelLocked)
                <span class="inline-block mt-1 text-[10px] font-700 text-mandarin bg-mandarin-soft rounded-full px-2 py-0.5">Level: HSK 1 (terkunci)</span>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5 text-xs text-ink-soft">
                <input type="checkbox" wire:model.live="autoPlay" class="rounded text-mandarin focus:ring-mandarin">
                Auto-play balasan
            </label>
            @if (! $session->ended_at)
                <button type="button" wire:click="finishSession" wire:confirm="Selesaikan sesi ini?"
                        class="text-sm text-ink-soft hover:text-ink">Selesai</button>
            @endif
            <a href="{{ route('conversation') }}" class="text-sm text-ink-soft">← Kembali</a>
        </div>
    </div>

    @if ($summary)
        <div class="bg-navy text-white rounded-2xl p-5 mb-4">
            <p class="font-display font-600 text-lg">Sesi selesai</p>
            <p class="text-white/70 text-sm mt-1">{{ $summary['exchange_count'] }} pertukaran pesan.</p>
            @if ($summary['notes'])
                <div class="mt-3 pt-3 border-t border-white/20 text-sm text-white/90 whitespace-pre-line">{{ $summary['notes'] }}</div>
            @endif
        </div>
    @endif

    @if ($this->analysisState)
        {{-- Polls only while an ended session's analysis is queued and unfinished (analyzed_at NULL), for at most Chat::ANALYSIS_POLL_SECONDS. --}}
        <div class="bg-card rounded-2xl p-5 mb-4" @if ($this->analysisState === 'pending') wire:poll.3s="pollAnalysis" @endif>
            <p class="font-display font-600 text-ink mb-2">Analisis kesalahan</p>

            @if ($this->analysisState === 'pending')
                <p class="text-sm text-ink-soft">Analisis kesalahan sedang diproses…</p>
            @elseif ($this->analysisState === 'timeout')
                <p class="text-sm text-ink-soft">Analisis belum selesai. Hasilnya akan muncul di halaman Progress setelah selesai diproses.</p>
                <button type="button" wire:click="pollAnalysis" class="mt-2 text-xs font-700 text-mandarin underline">Muat ulang</button>
            @elseif ($this->analysisState === 'failed')
                <p class="text-sm text-ink-soft">Analisis kesalahan gagal untuk sesi ini. Coba lagi nanti.</p>
            @elseif (empty($session->error_analysis['items']))
                <p class="text-sm text-ink">Tidak ada kesalahan yang terdeteksi di sesi ini. Mantap! 🎉</p>
            @else
                <ul class="space-y-3">
                    @foreach ($session->error_analysis['items'] as $item)
                        <li class="text-sm">
                            <span class="inline-block text-[10px] font-700 text-mandarin bg-mandarin-soft rounded-full px-2 py-0.5 mb-1">{{ \App\Enums\ErrorCategory::fromLoose($item['kategori'])->label() }}</span>
                            <p class="text-ink"><span class="line-through text-ink-faint">{{ $item['contoh'] }}</span> → <span class="font-700">{{ $item['koreksi'] }}</span></p>
                            @if ($item['penjelasan'])
                                <p class="text-xs text-ink-soft mt-0.5">{{ $item['penjelasan'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($this->analysisState === 'done' && ! empty($session->error_analysis['catatan']))
                <p class="text-xs text-ink-faint mt-3">{{ $session->error_analysis['catatan'] }}</p>
            @endif
        </div>
    @endif

    <div class="flex-1 overflow-y-auto space-y-3 mb-4 pr-1" id="conversation-scroll" x-data
         x-init="$el.scrollTop = $el.scrollHeight"
         x-on:message-sent.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($this->turns as $turn)
            @if ($turn->role === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[80%] bg-navy text-white rounded-2xl rounded-br-sm px-4 py-2.5 text-sm">
                        {{ $turn->content }}
                    </div>
                </div>
            @else
                <div class="flex justify-start">
                    <div class="max-w-[80%] bg-card rounded-2xl rounded-bl-sm px-4 py-2.5 text-sm text-ink border border-line-card">
                        <p>{{ $turn->content }}</p>
                        @if ($turn->translation)
                            <p class="text-ink-faint text-xs mt-1">🇮🇩 {{ $turn->translation }}</p>
                        @endif
                        <button type="button"
                                onclick="speak({{ Illuminate\Support\Js::from($turn->content) }}, '{{ $this->scenario['target_lang_code'] }}')"
                                class="mt-1.5 text-ink-faint hover:text-mandarin text-xs">🔊 Dengarkan</button>
                    </div>
                </div>
            @endif
        @empty
            <div class="text-center text-ink-soft text-sm py-10">Mulai percakapan dengan mengetik pesan di bawah.</div>
        @endforelse
    </div>

    @if (! $session->ended_at)
        <div x-data="voiceInput({{ \Illuminate\Support\Js::from($this->scenario['target_lang_code']) }})"
             x-on:ai-replied.window="handleAiReply($event.detail.text, $event.detail.langCode)">
            @if ($errorMessage)
                <p class="text-xs text-red-500 mb-2">{{ $errorMessage }}</p>
            @endif

            <p class="text-xs text-ink-faint mb-2" x-show="state === 'listening'">
                <span x-show="interimText" x-text="interimText"></span>
                <span x-show="!interimText">Mendengarkan... ngomong aja, tap mic lagi kalau udah selesai.</span>
            </p>
            <p class="text-xs text-red-500 mb-2" x-show="micError" x-text="micError"></p>

            <form wire:submit="sendMessage" class="flex gap-2">
                <input type="text" wire:model="newMessage" maxlength="500" placeholder="Ketik pesan..."
                       class="flex-1 rounded-xl border-line-card text-sm focus:border-mandarin focus:ring-mandarin"
                       @if ($this->remainingToday <= 0) disabled @endif>

                <button type="button" x-show="supported" x-on:click="toggleListening"
                        x-bind:disabled="(state === 'processing' || state === 'speaking') || {{ $this->remainingToday <= 0 ? 'true' : 'false' }}"
                        x-bind:class="{
                            'bg-navy text-white hover:bg-navy-soft': state === 'idle',
                            'bg-red-500 text-white animate-pulse': state === 'listening',
                            'bg-card-alt text-ink-faint': state === 'processing',
                            'bg-orange text-navy': state === 'speaking',
                        }"
                        class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center transition disabled:opacity-70"
                        x-bind:title="{
                            idle: 'Rekam suara',
                            listening: 'Tap untuk berhenti & kirim',
                            processing: 'Memproses...',
                            speaking: 'AI sedang bicara...',
                        }[state]">
                    <span x-show="state === 'processing'">⏳</span>
                    <span x-show="state === 'speaking'">🔊</span>
                    <span x-show="state === 'listening'">⏹️</span>
                    <span x-show="state === 'idle'">🎤</span>
                </button>

                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-navy text-white font-700 text-sm hover:bg-navy-soft transition disabled:opacity-50"
                        @if ($this->remainingToday <= 0) disabled @endif
                        wire:loading.attr="disabled" wire:target="sendMessage">
                    Kirim
                </button>
            </form>

            <p class="text-[11px] text-ink-faint mt-1.5" x-show="!supported">Voice input butuh Chrome/Edge.</p>
        </div>
    @endif
</div>
