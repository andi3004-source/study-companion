{{-- $baca: how a screen reader should say $value ("~16%" → "sekitar 16 persen"). --}}
@props(['icon', 'value', 'source', 'baca' => null])
<li class="rounded-card border border-brand-line bg-white p-4 sm:p-5 shadow-card flex flex-col sm:flex-row gap-3 sm:gap-4" data-kartu>
    <span class="w-12 h-12 rounded-full bg-brand-sky-icon text-brand-navy flex items-center justify-center shrink-0">
        <x-landing.icon :name="$icon" />
    </span>
    <div>
        <p class="text-3xl font-extrabold text-brand-navy leading-none">
            @if ($baca)
                <x-landing.ucap :tampil="$value" :baca="$baca" />
            @else
                {{ $value }}
            @endif
        </p>
        <p class="mt-2 text-ink">{{ $slot }}</p>
        <p class="mt-2 text-sm text-ink-soft">{{ $source }}</p>
    </div>
</li>
