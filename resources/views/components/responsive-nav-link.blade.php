@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-black text-start text-base font-medium text-black bg-yellow-300 focus:outline-none focus:text-black focus:bg-yellow-200 focus:border-black transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-black/85 hover:text-black hover:bg-yellow-300 hover:border-black/30 focus:outline-none focus:text-black focus:bg-yellow-300 focus:border-black/30 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
