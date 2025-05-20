<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Sauvegarder les Modifications
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
