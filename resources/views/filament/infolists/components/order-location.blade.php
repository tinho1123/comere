@php
    $record  = $getRecord();
    $company = $record->company;

    // Prioridade: endereço salvo no pedido (online) > endereço padrão do cliente
    $hasOrderAddress = $record->delivery_latitude && $record->delivery_longitude;

    if ($hasOrderAddress) {
        $clientLat = (float) $record->delivery_latitude;
        $clientLng = (float) $record->delivery_longitude;
        $clientAddressText = implode(', ', array_filter([
            trim(($record->delivery_street ?? '') . ', ' . ($record->delivery_number ?? '')),
            $record->delivery_complement,
            $record->delivery_neighborhood,
            ($record->delivery_city ?? '') . '/' . ($record->delivery_state ?? ''),
        ]));
        $clientZip = $record->delivery_zip;
        $addressLabel = 'Endereço de entrega';
    } else {
        $addr = $record->client?->defaultAddress()->first();
        $clientLat = $addr?->latitude  ? (float) $addr->latitude  : null;
        $clientLng = $addr?->longitude ? (float) $addr->longitude : null;
        $clientAddressText = $addr
            ? "{$addr->street}, {$addr->number}" . ($addr->complement ? ", {$addr->complement}" : '') . " — {$addr->neighborhood}, {$addr->city}/{$addr->state}"
            : null;
        $clientZip = $addr?->zip_code;
        $addressLabel = 'Endereço do Cliente';
    }

    $storeLat = $company?->latitude  ? (float) $company->latitude  : null;
    $storeLng = $company?->longitude ? (float) $company->longitude : null;

    $hasBoth   = $clientLat && $clientLng && $storeLat && $storeLng;
    $hasClient = $clientLat && $clientLng;

    $distance = null;
    if ($hasBoth) {
        $distance = app(\App\Services\DistanceService::class)->calculate(
            $storeLat, $storeLng, $clientLat, $clientLng
        );
    }

    $storeAddressText = $company?->address_street
        ? "{$company->address_street}, {$company->address_number} — {$company->address_neighborhood}, {$company->address_city}/{$company->address_state}"
        : null;

    $mapId = 'order-map-' . $record->id;
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
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">{{ $addressLabel }}</p>
                @if ($clientAddressText)
                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $clientAddressText }}</p>
                    @if ($clientZip)
                        <p class="text-xs text-gray-400 mt-1">CEP: {{ $clientZip }}</p>
                    @endif
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

        {{-- Mapa Leaflet --}}
        @if ($hasClient || $hasBoth)
            <div
                id="{{ $mapId }}"
                style="height: 320px; border-radius: 0.75rem; overflow: hidden; border: 1px solid #e5e7eb; z-index: 0;"
            ></div>

            <script>
                (function () {
                    function initMap() {
                        var clientLat = {{ $clientLat ?? 'null' }};
                        var clientLng = {{ $clientLng ?? 'null' }};
                        var storeLat  = {{ $storeLat  ?? 'null' }};
                        var storeLng  = {{ $storeLng  ?? 'null' }};

                        var map = L.map('{{ $mapId }}');

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        }).addTo(map);

                        var bounds = [];

                        // Marcador da loja (azul)
                        if (storeLat && storeLng) {
                            var storeIcon = L.divIcon({
                                className: '',
                                html: '<div style="width:14px;height:14px;background:#3b82f6;border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>',
                                iconAnchor: [7, 7]
                            });
                            L.marker([storeLat, storeLng], { icon: storeIcon })
                                .addTo(map)
                                .bindTooltip('{{ addslashes($company?->name ?? 'Loja') }}', { permanent: true, direction: 'top', offset: [0, -10] });
                            bounds.push([storeLat, storeLng]);
                        }

                        // Marcador do cliente (vermelho)
                        if (clientLat && clientLng) {
                            var clientIcon = L.divIcon({
                                className: '',
                                html: '<div style="width:14px;height:14px;background:#ef4444;border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>',
                                iconAnchor: [7, 7]
                            });
                            L.marker([clientLat, clientLng], { icon: clientIcon })
                                .addTo(map)
                                .bindTooltip('{{ addslashes($addressLabel) }}', { permanent: true, direction: 'top', offset: [0, -10] });
                            bounds.push([clientLat, clientLng]);
                        }

                        // Linha entre loja e cliente
                        if (storeLat && storeLng && clientLat && clientLng) {
                            L.polyline([[storeLat, storeLng], [clientLat, clientLng]], {
                                color: '#6366f1',
                                weight: 2,
                                dashArray: '6 4',
                                opacity: 0.7
                            }).addTo(map);
                        }

                        if (bounds.length > 1) {
                            map.fitBounds(bounds, { padding: [40, 40] });
                        } else if (bounds.length === 1) {
                            map.setView(bounds[0], 15);
                        }
                    }

                    function load() {
                        if (window.L) { initMap(); return; }
                        if (!document.getElementById('leaflet-css')) {
                            var link = document.createElement('link');
                            link.id  = 'leaflet-css';
                            link.rel = 'stylesheet';
                            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                            document.head.appendChild(link);
                        }
                        var script = document.createElement('script');
                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        script.onload = initMap;
                        document.head.appendChild(script);
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', load);
                    } else {
                        load();
                    }
                })();
            </script>
        @else
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800" style="height: 140px;">
                <p class="text-sm text-gray-400">Cadastre o endereço do cliente e da loja para visualizar o mapa.</p>
            </div>
        @endif

    </div>
</div>
