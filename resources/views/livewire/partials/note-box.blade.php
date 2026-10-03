<div>
    <label class="block text-xs font-700 text-ink-soft mb-1.5">Catatanmu</label>
    <textarea wire:model.live.debounce.700ms="noteDrafts.{{ $item['id'] }}"
              rows="5"
              placeholder="Tulis jawaban / catatan latihanmu di sini..."
              class="w-full rounded-xl border-line-card text-sm focus:border-mandarin focus:ring-mandarin"></textarea>
    @if (!empty($showWordCount))
        <p class="text-xs text-ink-faint mt-1" x-data="{ text: @entangle('noteDrafts.'.$item['id']) }">
            <span x-text="(text || '').trim().split(/\s+/).filter(Boolean).length"></span> kata
        </p>
    @endif
    <p class="text-xs text-good mt-1 opacity-0 transition-opacity" wire:loading.class.remove="opacity-0" wire:target="noteDrafts.{{ $item['id'] }}">Tersimpan otomatis...</p>
</div>
