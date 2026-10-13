@props(['icon', 'title', 'baca' => null])
<li class="flex items-start gap-3">
    <span class="w-11 h-11 rounded-full bg-brand-sky-icon text-brand-navy flex items-center justify-center shrink-0">
        <x-landing.icon :name="$icon" />
    </span>
    <span>
        <span class="block font-bold text-brand-navy">@if ($baca)<x-landing.ucap :tampil="$title" :baca="$baca" />@else{{ $title }}@endif</span>
        <span class="block text-sm text-ink-soft">{{ $slot }}</span>
    </span>
</li>
