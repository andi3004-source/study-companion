@props(['icon', 'title', 'status'])
<li class="rounded-card border border-brand-line bg-white p-4 sm:p-5 shadow-card flex flex-col sm:flex-row gap-3 sm:gap-4" data-kartu>
    <span class="w-12 h-12 rounded-full bg-brand-sky-icon text-brand-navy flex items-center justify-center shrink-0">
        <x-landing.icon :name="$icon" />
    </span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="font-bold text-brand-navy">{{ $title }}</h3>
            <x-landing.status-badge :status="$status" />
        </div>
        <p class="mt-1 text-sm text-ink-soft">{{ $slot }}</p>
    </div>
</li>
