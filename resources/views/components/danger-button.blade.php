<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-danger-custom']) }}>
    {{ $slot }}
</button>
