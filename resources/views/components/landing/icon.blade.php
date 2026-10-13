{{-- Outline icons for the home page (24px grid, currentColor). Always decorative: the text next to it carries the meaning. --}}
@props(['name'])
@php
    $paths = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'mic' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/>',
        'brain' => '<path d="M12 5.5V19M9.5 4A3 3 0 0 0 6.6 6.2 3 3 0 0 0 4.5 11a3 3 0 0 0 1.2 4.6A3 3 0 0 0 9.5 20c1.4 0 2.5-1 2.5-2.5M14.5 4a3 3 0 0 1 2.9 2.2 3 3 0 0 1 2.1 4.8 3 3 0 0 1-1.2 4.6A3 3 0 0 1 14.5 20c-1.4 0-2.5-1-2.5-2.5M12 5.5C12 4.7 11 4 9.5 4M12 5.5c0-.8 1-1.5 2.5-1.5M8.5 12H10M14 12h1.5"/>',
        'book' => '<path d="M3 5.5c2.5-1 5.5-1 9 1 3.5-2 6.5-2 9-1V19c-2.5-1-5.5-1-9 1-3.5-2-6.5-2-9-1zM12 6.5V20"/>',
        'shield-check' => '<path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
        'document' => '<path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6"/>',
        'pie' => '<path d="M11 4a8 8 0 1 0 9 9h-9z"/><path d="M14 3.3A8 8 0 0 1 20.7 10H14z"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.6v.4"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4zM8 10h8M8 13h5"/>',
        'chart' => '<path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"/>',
        'cards' => '<rect x="3" y="7" width="13" height="13" rx="2"/><path d="M8 4h11a2 2 0 0 1 2 2v11"/>',
        'translate' => '<path d="M4 5h8M8 3v2M6 5c0 4 2 7 5 8M10 5c-1 4-3 7-6 8M13 21l4-9 4 9M14.5 18h5"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'wave' => '<path d="M4 10v4M8 7v10M12 4v16M16 7v10M20 10v4"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'zoom' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.3-4.3M11 8v6M8 11h6"/>',
        'keyboard' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>',
        'contrast' => '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
        'speaker' => '<path d="M4 9h4l5-4v14l-5-4H4zM16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'w-6 h-6 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths[$name] !!}</svg>
