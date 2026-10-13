{{-- Small yellow label above a section heading ("Masalah", "Cara Kerja"). Navy on pale yellow: well above 4.5:1. --}}
@props(['tone' => 'yellow'])
<p {{ $attributes->merge(['class' => 'inline-block rounded-full px-3 py-1 text-sm font-bold text-brand-navy '.($tone === 'mint' ? 'bg-[#D3EEDC]' : 'bg-brand-yellow-soft')]) }} data-pil>{{ $slot }}</p>
