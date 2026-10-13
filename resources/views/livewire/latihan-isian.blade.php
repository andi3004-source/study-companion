<div data-voice-page="latihan-isian" class="max-w-4xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-1">Latihan Isian</h1>
    <p class="text-ink-soft text-sm mb-6">Ketik jawabanmu sendiri — melatih recall, bukan cuma pilih dari opsi.</p>

    {{-- Controls --}}
    <div class="grid grid-cols-1 sidebar:grid-cols-3 gap-3 mb-5">
        <div>
            <label for="isian-mode" class="block text-sm font-700 text-ink mb-1">Mode Soal</label>
            <select id="isian-mode" wire:model.live="modeSoal" class="w-full rounded-xl border-line-card text-sm focus:border-mandarin focus:ring-mandarin">
                <option value="hanzi_from_pinyin">Tebak Hanzi (Petunjuk: Pinyin)</option>
                <option value="pinyin_from_hanzi">Tebak Pinyin (Petunjuk: Hanzi)</option>
                <option value="both_from_meaning">Tebak Hanzi & Pinyin (Petunjuk: Arti)</option>
            </select>
        </div>
        <div>
            <label for="isian-jumlah" class="block text-sm font-700 text-ink mb-1">Jumlah Soal</label>
            <select id="isian-jumlah" wire:model.live="jumlah" class="w-full rounded-xl border-line-card text-sm focus:border-mandarin focus:ring-mandarin">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="all">Semua ({{ $this->totalPool }})</option>
            </select>
        </div>
        <div>
            <label for="isian-urutan" class="block text-sm font-700 text-ink mb-1">Urutan</label>
            <select id="isian-urutan" wire:model.live="urutan" class="w-full rounded-xl border-line-card text-sm focus:border-mandarin focus:ring-mandarin">
                <option value="sequential">Berurutan (Sesuai List)</option>
                <option value="random">Acak</option>
            </select>
        </div>
    </div>

    {{-- Summary --}}
    <p role="status" class="sr-only">
        @if ($checked)
            Hasil analisis: {{ $this->summary['benar'] }} benar, {{ $this->summary['salah'] }} salah, {{ $this->summary['kosong'] }} kosong dari {{ $this->summary['total'] }} soal.
        @endif
    </p>
    <div class="grid grid-cols-4 gap-2 mb-5">
        @foreach ([
            ['label' => 'Total Soal', 'value' => $this->summary['total'], 'class' => 'text-ink'],
            ['label' => 'Benar', 'value' => $this->summary['benar'], 'class' => 'text-good'],
            ['label' => 'Salah', 'value' => $this->summary['salah'], 'class' => 'text-red-700'],
            ['label' => 'Kosong', 'value' => $this->summary['kosong'], 'class' => 'text-ink-faint'],
        ] as $stat)
            <div class="bg-card rounded-xl px-3 py-2.5 text-center">
                <p class="text-lg font-display font-600 {{ $stat['class'] }}">{{ $checked ? $stat['value'] : '–' }}</p>
                <p class="text-xs text-ink-soft">{{ $stat['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Questions table --}}
    <div class="bg-card rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-line-card text-left text-ink-soft text-xs">
                    <th scope="col" class="px-3 py-2.5 font-700 w-10">No</th>
                    <th class="px-3 py-2.5 font-700">{{ $this->promptLabel() }}</th>
                    @foreach ($fields as $field)
                        <th class="px-3 py-2.5 font-700">{{ $fieldLabels[$field] }}</th>
                    @endforeach
                    <th class="px-3 py-2.5 font-700 min-w-[180px]">Hasil Analisis</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($questions as $i => $question)
                    <tr data-soal class="border-b border-line-card last:border-0 align-top">
                        <td class="px-3 py-2.5 text-ink-faint">{{ $i + 1 }}</td>
                        <td data-petunjuk class="px-3 py-2.5 {{ $this->promptLabel() === 'Pinyin' || $this->promptLabel() === 'Hanzi' ? 'font-display text-base' : '' }}">
                            {{ $question['prompt'] }}
                        </td>
                        @foreach ($fields as $field)
                            <td class="px-3 py-2.5">
                                <input type="text"
                                       wire:model="answers.{{ $i }}.{{ $field }}" data-isian="{{ $field }}"
                                       aria-label="{{ $fieldLabels[$field] }} untuk soal {{ $i + 1 }}"
                                       placeholder="{{ $fieldLabels[$field] }}"
                                       class="w-full rounded-lg border-line-card text-sm py-1.5 px-2 focus:border-mandarin focus:ring-mandarin {{ $field === 'hanzi' ? 'font-display' : '' }}">
                            </td>
                        @endforeach
                        <td data-hasil-soal class="px-3 py-2.5">
                            @php $result = $results[$i] ?? null; @endphp
                            @if (! $result)
                                <span class="text-ink-faint text-xs">—</span>
                            @elseif ($result['status'] === 'correct')
                                <span class="inline-block px-2 py-1 rounded-full bg-good/10 text-good text-xs font-700">✓ Semuanya Benar</span>
                            @elseif ($result['status'] === 'empty')
                                <span class="inline-block px-2 py-1 rounded-full bg-card-alt text-ink-faint text-xs font-700">Kosong</span>
                            @else
                                <span class="inline-block px-2 py-1 rounded-full bg-red-50 text-red-700 text-xs font-700">✗ Salah</span>
                                <span class="block text-xs text-ink-soft mt-1">{{ $result['detail'] }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-3 py-10 text-center text-ink-soft text-sm">Tidak ada kata untuk dilatih.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3 mt-5">
        <button type="button" wire:click="checkAnswers" wire:loading.attr="disabled" wire:target="checkAnswers"
                class="disabled:opacity-60 flex-1 py-3 rounded-xl bg-navy text-white font-700 text-sm hover:bg-navy-soft transition">
            <span wire:loading.remove wire:target="checkAnswers">Analisis Jawaban</span>
            <span wire:loading wire:target="checkAnswers">Menganalisis…</span>
        </button>
        <button type="button" wire:click="resetAnswers"
                class="px-5 py-3 rounded-xl bg-card-alt text-ink-soft font-700 text-sm hover:bg-line-card transition">
            Reset Isian
        </button>
    </div>
</div>
