<div class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 space-y-4"
     x-data="scanBenda({{ \Illuminate\Support\Js::from(route('scan.recognize')) }})">
    <h1 class="font-display font-600 text-2xl text-ink">Raba lalu dengar</h1>
    <p class="text-ink">Pegang satu benda, arahkan ke kamera, lalu ambil foto. Foto hanya diproses dan tidak disimpan.</p>

    <p class="rounded-2xl border-2 border-red-700 bg-red-50 p-4 text-ink">
        <strong class="text-red-700">Peringatan:</strong>
        Ini alat bantu belajar, bukan alat keselamatan. Jangan dipakai untuk obat atau benda berbahaya.
    </p>

    <div class="bg-card rounded-2xl p-5 space-y-3">
        <label for="scan-setuju" class="flex items-start gap-3 text-ink min-h-[44px]">
            <input type="checkbox" id="scan-setuju" x-model="setuju" class="mt-1 rounded text-navy focus:ring-navy">
            <span>Saya setuju foto dikirim ke layanan AI untuk dikenali. Foto tidak disimpan.</span>
        </label>

        {{-- aria-disabled (not disabled) keeps the button focusable so the reason can be announced. --}}
        <button type="button" x-on:click="ambil()"
                aria-disabled="true" x-bind:aria-disabled="(!setuju || memproses) ? 'true' : 'false'"
                aria-describedby="scan-alasan"
                class="w-full sm:w-auto px-6 py-3 rounded-xl bg-navy text-white font-700 aria-disabled:opacity-60 aria-disabled:cursor-not-allowed">
            <span aria-hidden="true">📷</span> Ambil foto
        </button>
        <p id="scan-alasan" class="text-sm text-ink-soft" x-show="!setuju">Tombol Ambil foto aktif setelah kotak persetujuan dicentang.</p>

        <label for="scan-putar-otomatis" class="flex items-start gap-3 text-ink min-h-[44px]">
            <input type="checkbox" id="scan-putar-otomatis" x-model="putarOtomatis" x-on:change="simpanPutarOtomatis()" class="mt-1 rounded text-navy focus:ring-navy">
            <span>Putar suara otomatis</span>
        </label>

        <input type="file" accept="image/*" capture="environment" x-ref="kamera" x-on:change="kirim($event)"
               class="sr-only" tabindex="-1" aria-hidden="true">
    </div>

    <div role="status" aria-live="polite" class="text-ink" x-text="status"></div>

    <p x-show="galat" x-cloak x-ref="galat" tabindex="-1" class="rounded-xl border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-700">
        <span aria-hidden="true">⚠</span> <span x-text="galat"></span>
    </p>

    <template x-if="hasil && hasil.benda">
        <section class="bg-card rounded-2xl p-5 space-y-4" aria-labelledby="scan-judul-hasil">
            <h2 id="scan-judul-hasil" x-ref="judulHasil" tabindex="-1" class="font-display font-600 text-xl text-ink"
                x-text="hasil.jenis === 'pasti' ? hasil.benda.indonesia : 'Mungkin: ' + hasil.benda.indonesia"></h2>
            <p x-show="hasil.jenis === 'mungkin'" class="font-700 text-ink">Saya belum yakin. Mau foto lagi?</p>

            <dl class="space-y-3">
                <div>
                    <dt class="text-xs font-700 text-ink-soft">Indonesia</dt>
                    <dd lang="id" class="text-ink text-lg" x-text="hasil.benda.indonesia"></dd>
                </div>
                <div>
                    <dt class="text-xs font-700 text-ink-soft">Inggris</dt>
                    <dd lang="en" class="text-ink text-lg" x-text="hasil.benda.inggris"></dd>
                </div>
                <div x-show="hasil.benda.ipa_inggris">
                    <dt class="text-xs font-700 text-ink-soft">IPA</dt>
                    <dd class="text-ink" x-text="hasil.benda.ipa_inggris"></dd>
                </div>
                <div>
                    <dt class="text-xs font-700 text-ink-soft">Mandarin</dt>
                    <dd lang="zh-Hans" class="text-ink text-2xl" x-text="hasil.benda.hanzi"></dd>
                </div>
                <div>
                    <dt class="text-xs font-700 text-ink-soft">Pinyin</dt>
                    <dd class="text-ink" x-text="hasil.benda.pinyin"></dd>
                </div>
            </dl>

            <div x-show="hasil.bentuk">
                <p class="text-ink" aria-describedby="scan-bentuk-catatan"><strong>Bentuk:</strong> <span x-text="hasil.bentuk"></span></p>
                <p id="scan-bentuk-catatan" class="text-sm text-ink-soft">Perkiraan dari foto, mungkin tidak persis dengan perabaan</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="dengarSemua()" class="px-4 rounded-xl bg-navy text-white font-700 text-sm">Dengarkan semua</button>
                <button type="button" x-on:click="dengar('id')" class="px-4 rounded-xl border border-line-card text-ink font-700 text-sm">Dengarkan Indonesia</button>
                <button type="button" x-on:click="dengar('en')" class="px-4 rounded-xl border border-line-card text-ink font-700 text-sm">Dengarkan Inggris</button>
                <button type="button" x-on:click="dengar('zh')" class="px-4 rounded-xl border border-line-card text-ink font-700 text-sm">Dengarkan Mandarin</button>
                <button type="button" x-on:click="fotoLagi()" class="px-4 rounded-xl border-2 border-navy text-navy font-700 text-sm">Foto lagi</button>
            </div>
        </section>
    </template>

    <template x-if="hasil && ! hasil.benda">
        <section class="bg-card rounded-2xl p-5 space-y-3" aria-labelledby="scan-judul-hasil">
            <h2 id="scan-judul-hasil" x-ref="judulHasil" tabindex="-1" class="font-display font-600 text-xl text-ink" x-text="hasil.pesan"></h2>
            <p x-show="hasil.panduan_kamera" class="text-ink"><strong>Saran kamera:</strong> <span x-text="hasil.panduan_kamera"></span></p>
            <p x-show="hasil.catatan" class="text-sm text-ink-soft"><strong>Catatan:</strong> <span x-text="hasil.catatan"></span></p>
            <button type="button" x-on:click="fotoLagi()" class="px-4 rounded-xl border-2 border-navy text-navy font-700 text-sm">Foto lagi</button>
        </section>
    </template>
</div>
