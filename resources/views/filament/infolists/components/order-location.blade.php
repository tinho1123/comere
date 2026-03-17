@php
    $record   = $getRecord();
    $addr     = $record->client?->defaultAddress()->first();
    $company  = $record->company;

    $clientLat  = $addr?->latitude;
    $clientLng  = $addr?->longitude;
    $storeLat   = $company?->latitude;
    $storeLng   = $company?->longitude;

    $hasClient  = $clientLat && $clientLng;
    $hasStore   = $storeLat && $storeLng;
    $hasBoth    = $hasClient && $hasStore;

    if ($hasBoth) {
        $distance = app(\App\Services\DistanceService::class)->calculate(
            (float) $storeLat, (float) $storeLng,
            (float) $clientLat, (float) $clientLng
        );
        $mapSrc = "https://maps.google.com/maps/dir/{$storeLat},{$storeLng}/{$clientLat},{$clientLng}?output=embed";
    } elseif ($hasClient) {
        $distance = null;
        $mapSrc = "https://maps.google.com/maps?q={$clientLat},{$clientLng}&z=15&output=embed";
    } else {
        $distance = null;
        $mapSrc = null;
    }

    $clientAddressText = $addr
        ? "{$addr->street}, {$addr->number}" . ($addr->complement ? ", {$addr->complement}" : '') . " — {$addr->neighborhood}, {$addr->city}/{$addr->state}"
        : null;

    $storeAddressText = $company?->address_street
        ? "{$company->address_street}, {$company->address_number} — {$company->address_neighborhood}, {$company->address_city}/{$company->address_state}"
        : null;
@endphp

<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
        <div class="flex items-center gap-3">
            <x-heroicon-o-map-pin class="h-5 w-5 text-primary-500" />
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Localização
            </h3>
        </div>
    </div>

    <div class="fi-section-content px-6 pb-6">

        {{-- Endereços e distância --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-4">
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Endereço do Cliente</p>
                @if ($clientAddressText)
                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $clientAddressText }}</p>
                    <p class="text-xs text-gray-400 mt-1">CEP: {{ $addr->zip_code }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">Não cadastrado</p>
                @endif
            </div>

            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Endereço da Loja</p>
                @if ($storeAddressText)
                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $storeAddressText }}</p>
                    <p class="text-xs text-gray-400 mt-1">CEP: {{ $company->address_zip }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">Não cadastrado</p>
                @endif
            </div>

            <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 p-4 flex flex-col justify-center items-center text-center">
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-400 mb-1">Distância</p>
                @if ($hasBoth)
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ number_format($distance, 2, ',', '.') }} km
                    </p>
                    <p class="text-xs text-primary-400 mt-1">em linha reta</p>
                @else
                    <p class="text-sm text-gray-400 italic">Coordenadas ausentes</p>
                @endif
            </div>
        </div>

        {{-- Mapa embutido --}}
        @if ($mapSrc)
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700" style="height: 320px;">
                <iframe
                    src="{{ $mapSrc }}"
                    width="100%"
                    height="320"
                    style="border:0;"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
        @else
            <div class="flex items-center justify-center rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800" style="height: 140px;">
                <p class="text-sm text-gray-400">Cadastre o endereço do cliente e da loja para visualizar o mapa.</p>
            </div>
        @endif

    </div>
</div>
