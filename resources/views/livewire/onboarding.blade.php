<div data-voice-page="onboarding" class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-lg bg-card rounded-3xl shadow-sm p-8">
        <div class="text-center mb-8">
            <div aria-hidden="true" class="w-14 h-14 mx-auto rounded-2xl bg-orange flex items-center justify-center font-display font-600 text-2xl text-navy mb-3">好</div>
            <h1 class="font-display font-600 text-2xl text-ink">Selamat Datang!</h1>
            <p class="text-ink-soft text-sm mt-1">Sebelum mulai, atur jadwal belajarmu.</p>
        </div>

        <form wire:submit="save" class="space-y-6">
            <div>
                <label for="onboarding-tanggal" class="block text-sm font-700 text-ink mb-2">Mulai belajar dari tanggal</label>
                <input type="date" id="onboarding-tanggal" wire:model="start_date" aria-describedby="onboarding-tanggal-catatan"
                       class="w-full rounded-xl border-line-card focus:border-mandarin focus:ring-mandarin">
                @error('start_date') <p role="alert" class="text-red-700 text-xs mt-1">{{ $message }}</p> @enderror
                <p id="onboarding-tanggal-catatan" class="text-xs text-ink-faint mt-1">Jadwal harianmu (Hari 1, Hari 2, dst) dihitung dari tanggal ini — bukan tanggal kalender tetap.</p>
            </div>

            <fieldset>
                <legend class="block text-sm font-700 text-ink mb-2">Pilih jalur belajar</legend>
                <div class="grid grid-cols-1 gap-2">
                    @foreach ([
                        'both' => ['Mandarin + English', 'Ikuti kedua jalur bersamaan (rekomendasi)'],
                        'mandarin' => ['Mandarin saja', 'Fokus HSK1, 26 hari'],
                        'english' => ['English saja', 'Fokus IELTS, 62 hari'],
                    ] as $value => [$label, $desc])
                        <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition
                                      {{ $track === $value ? 'border-mandarin bg-mandarin-soft' : 'border-line-card hover:bg-card-alt' }}">
                            <input type="radio" wire:model="track" value="{{ $value }}" class="mt-1 text-mandarin focus:ring-mandarin">
                            <span>
                                <span class="block font-700 text-sm text-ink">{{ $label }}</span>
                                <span class="block text-xs text-ink-soft">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit" class="w-full py-3 rounded-xl bg-navy text-white font-700 hover:bg-navy-soft transition">
                Mulai Belajar
            </button>
        </form>
    </div>
</div>
