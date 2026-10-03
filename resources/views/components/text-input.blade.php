@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line-card focus:border-mandarin focus:ring-mandarin rounded-xl shadow-sm text-sm']) }}>
