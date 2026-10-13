@php
    $mulai = config('registration.enabled') ? route('register') : route('login');
@endphp
<section aria-label="Mulai" class="max-w-6xl mx-auto px-4 pb-8">
    <div class="rounded-[1.75rem] bg-brand-navy text-white px-6 py-6 sm:px-8 flex flex-col sm:flex-row sm:items-center gap-5">
        <img src="{{ asset('images/landing/logo-buku-putih.png') }}" alt="" width="48" height="40" class="w-12 h-auto">
        <div class="flex-1">
            <p class="text-lg font-extrabold">Study Companion</p>
            <p class="text-sm text-white">Belajar lebih baik, bersama teknologi.</p>
        </div>
        <a href="{{ $mulai }}" data-tombol
           class="inline-flex items-center justify-center gap-2 min-h-[48px] px-6 rounded-xl bg-brand-yellow text-brand-navy font-bold">Mulai sekarang<x-landing.icon name="arrow-right" class="w-5 h-5" /></a>
    </div>
</section>
