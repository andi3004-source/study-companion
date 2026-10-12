<div class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-1">Progress</h1>
    <p class="text-ink-soft text-sm mb-6">Hari ke-{{ $day }} · {{ $totalTasks }} tugas selesai sepanjang perjalanan</p>

    @if ($streak > 0)
        <div class="flex items-center gap-2 mb-6 bg-orange/10 border border-orange/30 rounded-xl px-4 py-2.5 text-sm">
            <span class="text-lg" aria-hidden="true">🔥</span>
            <span class="font-700 text-ink">{{ $streak }} hari beruntun</span>
        </div>
    @endif

    <h2 class="font-display font-600 text-sm text-ink-soft mb-2">Progress Kartu</h2>
    <div class="space-y-3 mb-8">
        @foreach ([
            ['label' => 'Mandarin HSK1', 'data' => $mandarinDeck, 'color' => 'mandarin'],
            ['label' => 'Verb Tidak Beraturan', 'data' => $verbDeck, 'color' => 'english'],
            ['label' => 'Vocab Topik IELTS', 'data' => $vocabDeck, 'color' => 'english'],
        ] as $deck)
            <div class="bg-card rounded-xl p-4">
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="font-700 text-ink">{{ $deck['label'] }}</span>
                    <span class="text-ink-soft">{{ $deck['data']['known'] }}/{{ $deck['data']['total'] }}</span>
                </div>
                <div class="h-2 rounded-full bg-line-card overflow-hidden">
                    <div class="h-full rounded-full {{ $deck['color'] === 'mandarin' ? 'bg-mandarin' : 'bg-english' }}" style="width: {{ $deck['data']['percent'] }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="font-display font-600 text-sm text-ink-soft mb-2">Kesalahan terbanyakmu</h2>
    <div class="bg-card rounded-xl p-4 mb-8">
        @forelse ($topErrors as $error)
            <div class="flex items-start justify-between gap-3 {{ $loop->first ? '' : 'mt-3 pt-3 border-t border-line-card' }}">
                <div class="min-w-0 text-sm">
                    <p class="font-700 text-ink">{{ $error['label'] }}</p>
                    @if ($error['contoh'])
                        <p class="text-ink-soft text-xs mt-0.5">
                            Contoh: <span class="line-through text-ink-faint">{{ $error['contoh'] }}</span>
                            @if ($error['koreksi'])
                                → <span class="font-700 text-ink">{{ $error['koreksi'] }}</span>
                            @endif
                        </p>
                    @endif
                </div>
                <span class="shrink-0 text-xs font-700 text-mandarin bg-mandarin-soft rounded-full px-2 py-0.5">{{ $error['jumlah'] }}×</span>
            </div>
        @empty
            <p class="text-sm text-ink-soft">Belum ada data kesalahan dalam 7 hari terakhir. Selesaikan sesi Ngobrol Mandarin untuk melihat kesalahan yang paling sering kamu buat.</p>
        @endforelse
        @if (! empty($topErrors))
            <p class="text-xs text-ink-faint mt-3">Dari sesi Ngobrol Mandarin 7 hari terakhir.</p>
        @endif
        <a href="{{ route('game.error-hunter') }}" class="inline-block mt-3 text-sm font-700 text-mandarin hover:underline">Latih lewat game Pemburu Kesalahan →</a>
    </div>

    <h2 class="font-display font-600 text-sm text-ink-soft mb-2">Kategori yang sering meleset di game</h2>
    <div class="bg-card rounded-xl p-4 mb-8">
        @forelse ($gameWeak as $weak)
            <div class="flex items-center justify-between text-sm {{ $loop->first ? '' : 'mt-2' }}">
                <span class="font-700 text-ink">{{ $weak['label'] }}</span>
                <span class="text-xs text-ink-soft">{{ $weak['jumlah'] }}× salah</span>
            </div>
        @empty
            <p class="text-sm text-ink-soft">Belum ada jawaban salah di game dalam 30 hari terakhir.</p>
        @endforelse
    </div>

    <h2 class="font-display font-600 text-sm text-ink-soft mb-2">Ringkasan Fase</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8">
        <div class="bg-mandarin-soft rounded-xl p-4">
            <p class="font-700 text-mandarin text-sm mb-1">Mandarin</p>
            <p class="text-ink text-sm">
                @if ($mandarinStatus['type'] === 'done')
                    Selesai — semua 26 hari tuntas! 🎉
                @else
                    {{ ucfirst($mandarinStatus['type']) }}, minggu {{ $mandarinStatus['week_letter'] ?? '-' }}
                @endif
            </p>
        </div>
        <div class="bg-english-soft rounded-xl p-4">
            <p class="font-700 text-english text-sm mb-1">English</p>
            <p class="text-ink text-sm">
                @if ($englishStatus['type'] === 'done')
                    Selesai — semua 62 hari tuntas! 🎉
                @else
                    Fase {{ $englishStatus['phase'] }}, minggu {{ $englishStatus['week_number'] }}
                @endif
            </p>
        </div>
    </div>

    <h2 class="font-display font-600 text-sm text-ink-soft mb-2">Platform Belajar</h2>
    <div class="bg-card rounded-2xl divide-y divide-line-card overflow-hidden">
        @foreach ($platformLinks as $link)
            <a href="{{ $link->url }}" target="_blank" rel="noopener" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-card-alt">
                <span class="font-700 text-ink">{{ $link->name }}</span>
                <span class="text-ink-faint">↗</span>
            </a>
        @endforeach
    </div>
</div>
