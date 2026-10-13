{{--
    Feature status pill. Dark text on a pastel background (each pair is 5.5:1 or more).
    "Status:" is read by screen readers so the word never stands alone.
--}}
@props(['status'])
@php
    $warna = [
        'Berjalan' => 'bg-[#DDF3E4] text-[#1D6436]',
        'Draf' => 'bg-[#FDEBD3] text-[#8F4400]',
        'Eksperimen' => 'bg-[#ECE6FB] text-[#5534A3]',
        'Rencana' => 'bg-[#ECEEF2] text-[#475161]',
    ][$status] ?? 'bg-[#ECEEF2] text-[#475161]';
@endphp
<span {{ $attributes->merge(['class' => "inline-block rounded-full px-2.5 py-0.5 text-xs font-bold whitespace-nowrap {$warna}"]) }} data-pil><span class="sr-only">Status: </span>{{ $status }}</span>
