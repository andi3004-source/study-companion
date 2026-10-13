{{-- One "Cara Kerja" step. The number is part of the list order; the arrow between steps is decorative. --}}
@props(['number', 'icon', 'title', 'baca' => null, 'last' => false])
<li class="relative flex-1 flex flex-col items-start lg:items-center lg:text-center">
    <div class="relative">
        <span class="absolute -top-1 -left-1 w-7 h-7 rounded-full bg-brand-navy text-white text-sm font-bold flex items-center justify-center" aria-hidden="true">{{ $number }}</span>
        <span class="w-16 h-16 rounded-full bg-brand-sky-icon text-brand-navy flex items-center justify-center">
            <x-landing.icon :name="$icon" class="w-8 h-8" />
        </span>
    </div>
    <h3 class="mt-3 text-lg font-bold text-brand-navy"><span class="sr-only">Langkah {{ $number }}: </span>@if ($baca)<x-landing.ucap :tampil="$title" :baca="$baca" />@else{{ $title }}@endif</h3>
    <p class="mt-1 text-ink-soft">{{ $slot }}</p>
    @unless ($last)
        <x-landing.icon name="arrow-right" class="hidden lg:block absolute top-6 -right-6 w-6 h-6 text-ink-soft" />
    @endunless
</li>
