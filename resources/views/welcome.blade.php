<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Study Companion</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans bg-cream text-ink">

        {{-- Hero --}}
        <div class="min-h-screen flex flex-col">

            {{-- Navbar --}}
            <header class="flex items-center justify-between px-6 py-4 bg-navy shadow-sm">
                <a href="/" class="flex items-center gap-2">
                    <div class="w-10 h-10 rounded-xl bg-orange flex items-center justify-center font-display font-600 text-xl text-navy">好</div>
                    <span class="font-display font-600 text-lg text-white">Study Companion</span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-2">
                        @auth
                            <a href="{{ url('/dashboard') }}"
                               class="px-4 py-2 rounded-xl bg-orange text-navy font-700 text-sm hover:bg-orange/80 transition">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="px-4 py-2 rounded-xl text-white/80 hover:text-white font-600 text-sm transition">
                                Masuk
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="px-4 py-2 rounded-xl bg-orange text-navy font-700 text-sm hover:bg-orange/80 transition">
                                    Daftar Gratis
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            {{-- Hero section --}}
            <main class="flex-1 flex flex-col items-center justify-center text-center px-6 py-20">
                <div class="w-20 h-20 rounded-3xl bg-navy flex items-center justify-center font-display font-600 text-4xl text-orange mb-6 shadow-lg">好</div>

                <h1 class="font-display font-600 text-4xl sm:text-5xl text-navy max-w-2xl leading-tight">
                    Belajar Bahasa Lebih <span class="text-orange">Menyenangkan</span>
                </h1>
                <p class="mt-4 text-ink-soft text-lg max-w-xl">
                    Kuasai Mandarin HSK1 dan English IELTS dengan jadwal harian yang terstruktur, kartu kata, ujian, dan latihan percakapan.
                </p>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}"
                       class="mt-8 px-8 py-3.5 rounded-2xl bg-navy text-white font-700 text-base hover:bg-navy-soft transition shadow-md">
                        Mulai Belajar Sekarang →
                    </a>
                @endif

                {{-- Feature cards --}}
                <div class="mt-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 max-w-4xl w-full">
                    @foreach ([
                        ['🗂️', 'Kartu Kata', 'Flashcard interaktif untuk kosakata baru setiap hari.'],
                        ['📝', 'Ujian Harian', 'Tes singkat untuk memastikan vocab-mu benar-benar masuk.'],
                        ['💬', 'Ngobrol', 'Latihan percakapan dengan AI untuk meningkatkan fluency.'],
                        ['📈', 'Progress', 'Pantau streak dan perkembanganmu dari hari ke hari.'],
                    ] as [$icon, $title, $desc])
                        <div class="bg-card rounded-2xl p-5 text-left border border-line-card">
                            <span class="text-2xl">{{ $icon }}</span>
                            <h2 class="font-display font-600 text-navy mt-2 mb-1">{{ $title }}</h2>
                            <p class="text-ink-soft text-sm">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Language badges --}}
                <div class="mt-10 flex items-center gap-4 flex-wrap justify-center">
                    <span class="px-4 py-2 rounded-full bg-mandarin-soft text-mandarin font-700 text-sm">🇨🇳 Mandarin (HSK1)</span>
                    <span class="px-4 py-2 rounded-full bg-english-soft text-english font-700 text-sm">🇬🇧 English (IELTS)</span>
                </div>
            </main>

            <footer class="text-center py-6 text-xs text-ink-faint border-t border-line">
                Study Companion &mdash; Belajar bahasa setiap hari, satu langkah kecil sekaligus.
            </footer>
        </div>
    </body>
</html>
