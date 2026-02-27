<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary-custom']) }}>
    {{ $slot }}
</button>
