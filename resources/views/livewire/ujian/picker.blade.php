<div data-voice-page="ujian-picker" class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-1">Ujian</h1>
    <p class="text-ink-soft text-sm mb-6">Pilih mode ujian mock test.</p>

    <div class="space-y-3">
        @if ($this->hasTodayVocab)
            <a href="{{ route('ujian.run', ['mode' => 'today', 'day' => $day]) }}" data-voice-start="Tes Hari Ini"
               class="block rounded-2xl bg-navy text-white px-5 py-4 hover:bg-navy-soft transition">
                <span class="font-display font-600">Tes Hari Ini</span>
                <span class="block text-xs text-white/80 mt-0.5">Scoped ke vocab hari ini · beberapa soal · ~3-5 menit</span>
            </a>
        @endif

        <a href="{{ route('ujian.run', ['mode' => 'mandarin']) }}" data-voice-start="Ujian Mandarin"
           class="block rounded-2xl bg-mandarin-soft px-5 py-4 hover:bg-mandarin/10 transition">
            <span class="font-display font-600 text-mandarin">Mandarin</span>
            <span class="block text-xs text-ink-soft mt-0.5">50 soal acak dari 150 kata · ~15-25 menit</span>
        </a>

        <a href="{{ route('ujian.run', ['mode' => 'english']) }}" data-voice-start="Ujian English"
           class="block rounded-2xl bg-english-soft px-5 py-4 hover:bg-english/10 transition">
            <span class="font-display font-600 text-english">English</span>
            <span class="block text-xs text-ink-soft mt-0.5">50 soal acak dari verb & vocab IELTS · ~15-25 menit</span>
        </a>
    </div>

    @if ($this->recentScores->isNotEmpty())
        <div class="mt-8">
            <h2 class="font-display font-600 text-sm text-ink-soft mb-2">Riwayat Terakhir</h2>
            <div data-riwayat class="bg-card rounded-2xl divide-y divide-line-card overflow-hidden">
                @foreach ($this->recentScores as $score)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="font-700 text-ink capitalize">{{ $score->exam_mode }} · Hari {{ $score->day_number }}</span>
                        <span class="text-ink-soft">{{ $score->correct }}/{{ $score->total }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
