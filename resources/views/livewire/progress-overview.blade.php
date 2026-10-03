<div class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-1">Progress</h1>
    <p class="text-ink-soft text-sm mb-6">Hari ke-{{ $day }} · {{ $totalTasks }} tugas selesai sepanjang perjalanan</p>

    @if ($streak > 0)
        <div class="flex items-center gap-2 mb-6 bg-orange/10 border border-orange/30 rounded-xl px-4 py-2.5 text-sm">
            <span class="text-lg">🔥</span>
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
