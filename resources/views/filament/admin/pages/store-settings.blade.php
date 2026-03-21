<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3 items-center">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Salvar configurações
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-map-pin"
                x-data
                @click="
                    if (!navigator.geolocation) {
                        alert('Geolocalização não suportada neste navegador.');
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            $wire.setGpsCoords(pos.coords.latitude, pos.coords.longitude);
                        },
                        () => alert('Não foi possível obter a localização.')
                    );
                "
            >
                Localizar pelo GPS
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
