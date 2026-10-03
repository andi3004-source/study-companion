<div class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-1">AI Conversation Partner</h1>
    <p class="text-ink-soft text-sm mb-6">Latihan ngobrol bebas sesuai skenario. AI akan tetap dalam karakter dan menyisipkan koreksi kalau ada kesalahan.</p>

    <div class="flex gap-2 mb-5">
        <button type="button" wire:click="$set('lang', 'mandarin')"
                class="flex-1 py-2.5 rounded-xl font-700 text-sm transition {{ $lang === 'mandarin' ? 'bg-mandarin text-white' : 'bg-mandarin-soft text-mandarin' }}">
            Mandarin
        </button>
        <button type="button" wire:click="$set('lang', 'english')"
                class="flex-1 py-2.5 rounded-xl font-700 text-sm transition {{ $lang === 'english' ? 'bg-english text-white' : 'bg-english-soft text-english' }}">
            English
        </button>
    </div>

    <div class="space-y-3">
        @foreach ($this->scenarios as $key => $scenario)
            <button type="button" wire:click="selectScenario('{{ $key }}')"
                    class="w-full text-left block rounded-2xl bg-card px-5 py-4 hover:bg-card-alt transition border border-line-card">
                <span class="font-display font-600 text-ink">{{ $scenario['label'] }}</span>
                <span class="block text-xs text-ink-soft mt-0.5">Mulai sesi percakapan baru</span>
            </button>
        @endforeach

        <button type="button" wire:click="startRandom" wire:loading.attr="disabled" wire:target="startRandom"
                class="w-full text-left block rounded-2xl bg-orange/10 px-5 py-4 hover:bg-orange/20 transition border border-dashed border-orange disabled:opacity-60">
            <span class="font-display font-600 text-navy" wire:loading.remove wire:target="startRandom">🎲 Ngobrol Bebas</span>
            <span class="font-display font-600 text-navy" wire:loading wire:target="startRandom">Menyiapkan obrolan...</span>
            <span class="block text-xs text-ink-soft mt-0.5" wire:loading.remove wire:target="startRandom">Topik acak, AI mulai duluan — gak perlu mikir dulu</span>
        </button>
    </div>
</div>
