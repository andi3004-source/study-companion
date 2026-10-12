<div class="max-w-3xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="font-display font-600 text-2xl text-ink">Halo, {{ auth()->user()->name }} <span aria-hidden="true">👋</span></h1>
            <p class="text-ink-soft text-sm">{{ now()->translatedFormat('l, d F Y') }} · Hari ke-{{ $this->day }}</p>
        </div>
        <button type="button" wire:click="$set('showDayPicker', true)" class="text-sm font-700 text-navy bg-card-alt px-3 py-2 rounded-xl border border-line-card hover:bg-line-card">
            Pilih Hari
        </button>
    </div>

    {{-- Streak --}}
    @php $streak = $this->progress->streak(auth()->user(), $this->day); @endphp
    @if ($streak > 0)
        <div class="flex items-center gap-2 mb-6 bg-orange/10 border border-orange/30 rounded-xl px-4 py-2.5 text-sm">
            <span class="text-lg" aria-hidden="true">🔥</span>
            <span class="font-700 text-ink">{{ $streak }} hari beruntun</span>
            <span class="text-ink-soft">— terus lanjutkan!</span>
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        @if (in_array(auth()->user()->track, ['mandarin', 'both']))
            <div class="bg-mandarin-soft rounded-2xl p-4">
                <p class="font-display font-600 text-mandarin text-sm mb-1">Mandarin (HSK1)</p>
                @if ($this->mandarinStatus['type'] === 'done')
                    <p class="text-ink font-700">Selesai! 🎉</p>
                @else
                    <p class="text-2xl font-display font-600 text-ink">{{ $this->progress->mandarinDaysRemaining($this->day) }} <span class="text-sm font-sans font-600 text-ink-soft">hari lagi</span></p>
                @endif
            </div>
        @endif
        @if (in_array(auth()->user()->track, ['english', 'both']))
            <div class="bg-english-soft rounded-2xl p-4">
                <p class="font-display font-600 text-english text-sm mb-1">English (IELTS)</p>
                @if ($this->englishStatus['type'] === 'done')
                    <p class="text-ink font-700">Selesai! 🎉</p>
                @else
                    <p class="text-2xl font-display font-600 text-ink">{{ $this->progress->englishDaysRemaining($this->day) }} <span class="text-sm font-sans font-600 text-ink-soft">hari lagi</span></p>
                @endif
            </div>
        @endif
    </div>

    {{-- Today exam CTA --}}
    @php
        $hasVocabToday = collect($this->items)->contains(fn ($i) => $i['type'] === 'vocab');
    @endphp
    @if ($hasVocabToday)
        <a href="{{ route('ujian.run', ['mode' => 'today', 'day' => $this->day]) }}"
           class="block mb-6 rounded-2xl bg-navy text-white px-5 py-4 hover:bg-navy-soft transition">
            <span class="font-display font-600"><span aria-hidden="true">📝</span> Tes Hari Ini</span>
            <span class="block text-xs text-white/80 mt-0.5">Uji vocab baru hari ini</span>
        </a>
    @endif

    {{-- Checklist --}}
    <div class="bg-card rounded-2xl divide-y divide-line-card overflow-hidden">
        @forelse ($this->items as $item)
            @php $checked = $this->checklistState->get($item['id'])?->is_checked ?? false; @endphp
            <div class="flex items-center gap-3 px-4 py-3.5">
                <button type="button" wire:click="toggleChecklist('{{ $item['id'] }}')"
                        aria-label="Tandai selesai: {{ $item['title'] }}" aria-pressed="{{ $checked ? 'true' : 'false' }}"
                        class="shrink-0 w-6 h-6 rounded-lg border-2 flex items-center justify-center transition
                               {{ $checked ? 'bg-good border-good text-white' : 'border-ink-faint text-transparent hover:border-ink' }}">
                    <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </button>
                <button type="button" wire:click="openItem('{{ $item['id'] }}')" class="flex-1 min-w-0 text-left">
                    <span aria-hidden="true" class="inline-block w-2 h-2 rounded-full mr-1.5 {{ $item['lang'] === 'mandarin' ? 'bg-mandarin' : 'bg-english' }}"></span>
                    <span class="font-700 text-sm {{ $checked ? 'text-ink-faint line-through' : 'text-ink' }}">{{ $item['title'] }}</span>
                    @if ($item['subtitle'])
                        <span class="block text-xs text-ink-soft ml-3.5">{{ $item['subtitle'] }}</span>
                    @endif
                </button>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-ink-soft text-sm">Tidak ada kegiatan untuk hari ini.</div>
        @endforelse
    </div>

    {{-- Day picker panel --}}
    @if ($showDayPicker)
        <div class="fixed inset-0 bg-black/40 z-50 flex items-end sidebar:items-center justify-center" wire:click.self="$set('showDayPicker', false)"
             x-data="modalDialog()" x-on:keydown.escape="$wire.set('showDayPicker', false)">
            <div role="dialog" aria-modal="true" aria-labelledby="judul-pilih-hari" class="bg-card w-full sidebar:max-w-md sidebar:rounded-3xl rounded-t-3xl max-h-[80vh] overflow-y-auto p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="judul-pilih-hari" class="font-display font-600 text-lg">Pilih Hari</h2>
                    <button type="button" wire:click="$set('showDayPicker', false)" aria-label="Tutup" data-autofocus class="text-ink-soft text-xl leading-none"><span aria-hidden="true">✕</span></button>
                </div>
                <div class="space-y-3">
                    @foreach ($this->weekRanges as $week)
                        <div>
                            <p class="text-xs font-700 text-ink-soft mb-1.5">Minggu {{ $week['week'] }} (Hari {{ $week['start'] }}–{{ $week['end'] }})</p>
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(44px,1fr))] gap-1.5">
                                @for ($d = $week['start']; $d <= $week['end']; $d++)
                                    <button type="button" wire:click="goToDay({{ $d }})" aria-label="Hari {{ $d }}" @if ($d === $this->day) aria-current="date" @endif
                                            class="aspect-square rounded-lg text-xs font-700 flex items-center justify-center transition
                                                   {{ $d === $this->day ? 'bg-navy text-white' : 'bg-card-alt text-ink hover:bg-line-card' }}">
                                        {{ $d }}
                                    </button>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Detail modal --}}
    @if ($this->activeItem)
        @include('livewire.partials.item-detail', ['item' => $this->activeItem])
    @endif
</div>
