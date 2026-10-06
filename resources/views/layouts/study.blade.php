<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Study Companion' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-cream text-ink font-sans antialiased min-h-screen">
    <div class="sidebar:flex min-h-screen">
        {{-- Desktop sidebar --}}
        <aside class="hidden sidebar:flex sidebar:flex-col w-[252px] shrink-0 bg-navy text-white px-5 py-6">
            <div class="flex items-center gap-2 mb-8 px-2">
                <div class="w-9 h-9 rounded-xl bg-orange flex items-center justify-center font-display font-600 text-navy">好</div>
                <span class="font-display font-600 text-lg">Study Companion</span>
            </div>

            <nav class="flex flex-col gap-1">
                @php
                    $navItems = [
                        ['route' => 'dashboard', 'label' => 'Hari Ini', 'icon' => '🏠'],
                        ['route' => 'cards.index', 'label' => 'Kartu', 'icon' => '🗂️'],
                        ['route' => 'kamus', 'label' => 'Kamus', 'icon' => '🔎'],
                        ['route' => 'ujian', 'label' => 'Ujian', 'icon' => '📝'],
                        ['route' => 'latihan-isian', 'label' => 'Isian', 'icon' => '🧠'],
                        ['route' => 'game.error-hunter', 'label' => 'Game', 'icon' => '🎯'],
                        ['route' => 'conversation', 'label' => 'Ngobrol', 'icon' => '💬'],
                        ['route' => 'pronunciation-check', 'label' => 'Ucapan', 'icon' => '🎤'],
                        ['route' => 'progress', 'label' => 'Progress', 'icon' => '📈'],
                    ];
                @endphp
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-600 text-sm transition
                              {{ request()->routeIs($item['route'].'*') ? 'bg-navy-soft text-white' : 'text-white/70 hover:bg-navy-soft/60 hover:text-white' }}">
                        <span>{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto pt-6">
                <div class="rounded-2xl bg-navy-deep p-4 mb-4">
                    <p class="font-hand text-2xl text-orange leading-none">Keep going!</p>
                    <p class="text-xs text-white/60 mt-1">Hari {{ auth()->user()->dayNumber() ?? '-' }} sejak mulai</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-white/50 hover:text-white/80 px-3">Keluar</button>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            <header class="sidebar:hidden flex items-center justify-between px-4 py-3 bg-navy text-white sticky top-0 z-30">
                <span class="font-display font-600">Study Companion</span>
                <span class="text-xs text-white/70">Hari {{ auth()->user()->dayNumber() ?? '-' }}</span>
            </header>

            <main class="pb-24 sidebar:pb-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{--
        Mobile bottom tab bar: 8 items don't fit a bottom tab bar (it was
        designed for 4-5), so only 4 main ones show directly; the rest live
        behind a "Lainnya" sheet. Both slices are derived from the same
        $navItems the desktop sidebar uses above — never a separately
        maintained list — so icons/labels/routes can't drift between the two.
        Desktop sidebar itself is untouched.
    --}}
    @php
        $mobileMainRoutes = ['dashboard', 'cards.index', 'kamus', 'conversation'];
        $mobileMainItems = collect($navItems)
            ->sortBy(fn ($item) => array_search($item['route'], $mobileMainRoutes))
            ->filter(fn ($item) => in_array($item['route'], $mobileMainRoutes, true))
            ->values();
        $mobileMoreItems = collect($navItems)
            ->reject(fn ($item) => in_array($item['route'], $mobileMainRoutes, true))
            ->values();
        $mobileMoreActive = $mobileMoreItems->contains(fn ($item) => request()->routeIs($item['route'].'*'));
    @endphp
    <nav class="sidebar:hidden fixed bottom-0 inset-x-0 bg-card border-t border-line-card flex justify-around py-2 z-40" x-data="{ moreOpen: false }">
        @foreach ($mobileMainItems as $item)
            <a href="{{ route($item['route']) }}"
               class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-600
                      {{ request()->routeIs($item['route'].'*') ? 'text-mandarin' : 'text-ink-faint' }}">
                <span class="text-lg leading-none">{{ $item['icon'] }}</span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach

        <button type="button" x-on:click="moreOpen = true"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-600
                       {{ $mobileMoreActive ? 'text-mandarin' : 'text-ink-faint' }}">
            <span class="text-lg leading-none">⋯</span>
            <span>Lainnya</span>
        </button>

        <template x-if="moreOpen">
            <div class="fixed inset-0 bg-black/40 z-50 flex items-end" x-on:click.self="moreOpen = false">
                <div class="bg-card rounded-t-3xl w-full p-4 pb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-display font-600 text-base text-ink">Lainnya</h3>
                        <button type="button" x-on:click="moreOpen = false" class="text-ink-faint text-xl leading-none">✕</button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($mobileMoreItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="flex flex-col items-center gap-1 px-2 py-3 rounded-xl text-xs font-600
                                      {{ request()->routeIs($item['route'].'*') ? 'bg-mandarin-soft text-mandarin' : 'text-ink-soft hover:bg-card-alt' }}">
                                <span class="text-xl leading-none">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </template>
    </nav>

    @livewireScripts
    @stack('scripts')
</body>
</html>
