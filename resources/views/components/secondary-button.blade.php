<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn-secondary-custom']) }}>
    {{ $slot }}
</button>
