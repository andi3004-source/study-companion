<div data-voice-page="game-hub" class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 space-y-4">
    <h1 class="font-display font-600 text-2xl text-ink">Game</h1>
    <p class="text-ink">Semua game bisa dimainkan dengan keyboard, pembaca layar, dan suara, tanpa batas waktu.</p>

    <ul class="space-y-4">
        @foreach ($games as $game)
            <li class="bg-card rounded-2xl border border-line-card p-5 space-y-2">
                <h2 class="font-display font-600 text-xl text-ink">{{ $game['judul'] }}</h2>
                <p class="text-ink">{{ $game['penjelasan'] }}</p>
                <p><span class="inline-block rounded-full border-2 border-navy px-3 py-0.5 text-sm font-700 text-navy">Status: {{ $game['status'] }}</span></p>
                <p class="text-ink-soft">{{ $game['cara'] }}</p>
                @if ($game['available'])
                    <a href="{{ route($game['route']) }}" data-voice-start="{{ $game['judul'] }}"
                       class="inline-flex items-center justify-center min-h-[44px] px-5 py-3 rounded-xl bg-navy text-white font-700">{{ $game['tautan'] }}</a>
                @else
                    <p role="status" class="rounded-xl bg-card-alt border border-line-card px-4 py-3 text-ink">{{ $game['kosong'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</div>
