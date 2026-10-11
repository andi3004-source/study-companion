@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-700 text-sm text-ink mb-1']) }}>
    {{ $value ?? $slot }}
</label>
