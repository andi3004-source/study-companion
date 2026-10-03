<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2.5 bg-navy border border-transparent rounded-xl font-700 text-sm text-white hover:bg-navy-soft focus:bg-navy-soft active:bg-navy-deep focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
