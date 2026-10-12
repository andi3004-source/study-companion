<div class="max-w-3xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 space-y-6">
    <h1 class="font-display font-600 text-2xl text-ink">Bantuan</h1>
    <p class="text-ink">Belajar bahasa dengan keyboard dan suara. Dirancang agar mudah diakses.</p>

    <section aria-labelledby="bantuan-keyboard" class="bg-card rounded-2xl p-5 space-y-3">
        <h2 id="bantuan-keyboard" class="font-display font-600 text-lg text-ink">Memakai dengan keyboard dan pembaca layar</h2>
        <p class="text-ink">Setiap halaman diawali tautan <strong>Lewati ke konten</strong>. Di halaman percakapan ada juga <strong>Lewati ke kolom tulis</strong>. Tautan ini muncul saat kamu menekan Tab pertama kali.</p>
        <p class="text-ink">Halaman punya bagian header, navigasi, konten utama, dan footer, sehingga pembaca layar bisa melompat antarbagian. Di percakapan, pesan baru dibacakan otomatis dengan nama pengirimnya (<em>Kamu</em> atau <em>Teman bicara</em>), dan status seperti "Teman sedang membalas" atau "Analisis selesai" diumumkan tanpa memindahkan fokus.</p>
        <p class="text-ink">Tidak ada pintasan satu tombol; semua tindakan memakai tombol biasa yang bisa dijangkau dengan Tab.</p>
    </section>

    <section aria-labelledby="bantuan-tombol" class="bg-card rounded-2xl p-5 space-y-3">
        <h2 id="bantuan-tombol" class="font-display font-600 text-lg text-ink">Tombol standar</h2>
        <table class="w-full text-left text-ink">
            <caption class="sr-only">Tombol keyboard dan fungsinya</caption>
            <thead>
                <tr class="border-b border-line-card">
                    <th scope="col" class="py-2 pr-4">Tombol</th>
                    <th scope="col" class="py-2">Fungsi</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Tab</kbd></th><td class="py-2">Pindah ke tautan, tombol, atau kolom berikutnya.</td></tr>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Shift</kbd>+<kbd>Tab</kbd></th><td class="py-2">Kembali ke elemen sebelumnya.</td></tr>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Enter</kbd></th><td class="py-2">Menjalankan tombol atau tautan. Di kolom tulis percakapan, Enter mengirim pesan (tidak mengirim saat papan ketik Mandarin/IME masih menyusun huruf).</td></tr>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Shift</kbd>+<kbd>Enter</kbd></th><td class="py-2">Baris baru di kolom tulis percakapan.</td></tr>
                <tr><th scope="row" class="py-2 pr-4"><kbd>Escape</kbd></th><td class="py-2">Menutup panel Tampilan dan mengembalikan fokus ke tombolnya.</td></tr>
            </tbody>
        </table>
    </section>

    <section aria-labelledby="bantuan-suara" class="bg-card rounded-2xl p-5 space-y-3">
        <h2 id="bantuan-suara" class="font-display font-600 text-lg text-ink">Memakai input suara</h2>
        <p class="text-ink">Input suara memakai Web Speech API dan hanya berjalan di <strong>Chrome atau Edge</strong>. Izinkan mikrofon saat browser meminta.</p>
        <ol class="list-decimal pl-6 text-ink space-y-1">
            <li>Di percakapan, tekan tombol <strong>Mulai bicara</strong>.</li>
            <li>Bicaralah. Tombol berubah menjadi <strong>Berhenti</strong> (berstatus ditekan).</li>
            <li>Tekan <strong>Berhenti</strong>; apa yang terdengar langsung dikirim apa adanya.</li>
        </ol>
        <p class="text-ink">Setiap balasan punya tombol <strong>Dengarkan</strong> untuk memutar suaranya.</p>
    </section>

    <section aria-labelledby="bantuan-mode-suara" class="bg-card rounded-2xl p-5 space-y-3">
        <h2 id="bantuan-mode-suara" class="font-display font-600 text-lg text-ink">Mode suara</h2>
        <p class="text-ink">Mode suara adalah pelengkap untuk memakai aplikasi dengan perintah lisan. Pembaca layar dan keyboard tetap jalur utama. Mode ini mati secara bawaan; nyalakan dengan tombol <strong>Mode suara</strong> di bagian paling atas halaman, dan matikan dengan tombol <strong>Berhenti</strong> atau perintah "berhenti". Mode suara dan Mode pembaca layar tidak bisa aktif bersamaan.</p>
        <h3 class="font-700 text-ink">Perintah</h3>
        <ul class="list-disc pl-6 text-ink space-y-1">
            <li>"Buka" lalu nama halaman: hari ini, kartu, kamus, ujian, isian, game, pemburu kesalahan, dengar dan pilih, ngobrol, ucapan, kata bicara, progress, bantuan (dan raba lalu dengar bila fitur itu aktif).</li>
            <li>"Baca halaman", "daftar tindakan", lalu "nomor satu" sampai "nomor dua belas" (atau "pilihan a" sampai "d"); "lanjut" untuk daftar berikutnya.</li>
            <li>"Kembali", "ulangi", "perintah", "berhenti"; "ya" atau "tidak" untuk konfirmasi.</li>
            <li>Di kolom isian: "isi" untuk mulai mendikte dan "selesai isi" untuk berhenti. Teks tidak dikirim otomatis.</li>
            <li>Kata bicara: "cari" lalu kata, "dengarkan inggris". Game: "satu" sampai "empat", "ulangi", "lanjut", "penjelasan". Kartu: "balik", "berikutnya", "dengarkan". Ngobrol: ucapkan pesanmu; "selesai" untuk menyelesaikan sesi.</li>
        </ul>
        <h3 class="font-700 text-ink">Batas</h3>
        <ul class="list-disc pl-6 text-ink space-y-1">
            <li>Hanya berjalan di Chrome atau Edge dan butuh internet.</li>
            <li>Suaramu diproses oleh penyedia pengenalan suara browser. Aplikasi ini hanya menerima teksnya untuk dikenali sebagai perintah dan tidak menyimpan suara maupun teks itu.</li>
            <li>Akurasi pengenalan bervariasi. Suara Mandarin harus terpasang di perangkat agar teks Mandarin terbaca benar.</li>
            <li>Setelah pindah halaman, mikrofon perlu satu ketukan atau Enter pada tombol Mode suara untuk aktif lagi.</li>
            <li>Belum diuji dengan pengguna tunanetra.</li>
        </ul>
    </section>

    <section aria-labelledby="bantuan-tampilan" class="bg-card rounded-2xl p-5 space-y-3">
        <h2 id="bantuan-tampilan" class="font-display font-600 text-lg text-ink">Mengatur tampilan</h2>
        <p class="text-ink">Buka tombol <strong>Tampilan</strong> di header. Pilihan tersimpan di browser ini:</p>
        <ul class="list-disc pl-6 text-ink space-y-1">
            <li><strong>Ukuran teks</strong>: Normal, Besar, atau Sangat besar.</li>
            <li><strong>Mode kontras tinggi</strong>: teks hitam di latar putih.</li>
            <li><strong>Mode pembaca layar</strong>: balasan tidak diputar otomatis; suara hanya diputar saat kamu menekan tombol putar.</li>
        </ul>
        <p class="text-ink">Animasi dikurangi otomatis bila sistem operasimu diatur untuk mengurangi gerakan.</p>
    </section>

    <section aria-labelledby="bantuan-pernyataan" class="bg-card rounded-2xl p-5 space-y-3">
        <h2 id="bantuan-pernyataan" class="font-display font-600 text-lg text-ink">Pernyataan aksesibilitas</h2>
        <p class="text-ink">Pernyataan aksesibilitas (draf): kami berupaya mengikuti pedoman WCAG 2.2 AA. Aplikasi ini dirancang untuk pembaca layar; pengujian oleh tim dengan Narator sedang berlangsung dan belum dilakukan dengan pengguna tunanetra. Masukan Anda sangat kami harapkan.</p>
    </section>
</div>
