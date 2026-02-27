<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black leading-tight">
            {{ __('Panel de usuario') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-black">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-yellow-400 overflow-hidden shadow-sm sm:rounded-lg border border-yellow-300">
                <div class="p-6 text-black">
                    {{ __('Sesion iniciada correctamente.') }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
