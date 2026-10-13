{{--
    Shows one text and gives screen readers another, for symbols they read badly:
    "~16%" → "sekitar 16 persen", "0 / 4.991" → "0 dari 4991", "1–3" → "1 sampai 3".
    See docs/pedoman-teks-pembaca-layar.md (aturan 4).
--}}
@props(['tampil', 'baca'])
<span aria-hidden="true">{{ $tampil }}</span><span class="sr-only">{{ $baca }}</span>
