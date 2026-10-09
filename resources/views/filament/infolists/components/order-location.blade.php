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
@endphp

<style>
    /* Classes utilitárias do Tailwind usadas aqui não existem no CSS compilado
       do painel admin (que só publica as classes que o próprio Filament usa
       internamente) — por isso este componente define suas próprias regras,
       escopadas sob .ol-root, em vez de depender de classes como "grid",
       "rounded-lg" ou "bg-gray-50". */
    .ol-root .ol-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    @media (min-width: 640px) {
        .ol-root .ol-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    .ol-root .ol-card {
        border-radius: 0.5rem;
        padding: 1rem;
        background: #f9fafb;
    }
    html.dark .ol-root .ol-card {
        background: #1f2937;
    }
    .ol-root .ol-card--distance {
        background: #eef2ff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    html.dark .ol-root .ol-card--distance {
        background: rgba(99, 102, 241, 0.12);
    }
    .ol-root .ol-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9ca3af;
        margin: 0 0 0.25rem;
    }
    .ol-root .ol-label--primary {
        color: #818cf8;
    }
    .ol-root .ol-text {
        font-size: 0.875rem;
        color: #1f2937;
        margin: 0;
    }
    html.dark .ol-root .ol-text {
        color: #e5e7eb;
    }
    .ol-root .ol-text-muted {
        font-size: 0.75rem;
        color: #9ca3af;
        margin: 0.25rem 0 0;
    }
    .ol-root .ol-text-italic {
        font-size: 0.875rem;
        color: #9ca3af;
        font-style: italic;
        margin: 0;
    }
    .ol-root .ol-distance {
        font-size: 1.5rem;
        font-weight: 700;
        color: #4f46e5;
        margin: 0;
    }
    html.dark .ol-root .ol-distance {
        color: #a5b4fc;
    }
    .ol-root .ol-maps-link {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 500;
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: #6366f1;
    }
    .ol-root .ol-maps-link:hover {
        text-decoration: underline;
    }
    .ol-root .ol-empty-map {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        border: 1px dashed #d1d5db;
        background: #f9fafb;
        height: 140px;
    }
    html.dark .ol-root .ol-empty-map {
        border-color: #374151;
        background: #1f2937;
    }
</style>

<div class="ol-root fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
        <div class="flex items-center gap-3">
            <x-heroicon-o-map-pin style="width: 1.25rem; height: 1.25rem; color: #6366f1;" />
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Localização
            </h3>
        </div>
    </div>

    <div class="fi-section-content px-6 pb-6">

        {{-- Endereços e distância --}}
        <div class="ol-grid">
            <div class="ol-card">
                <p class="ol-label">{{ $addressLabel }}</p>
                @if ($clientAddressText)
                    <p class="ol-text">{{ $clientAddressText }}</p>
                    @if ($clientZip)
                        <p class="ol-text-muted">CEP: {{ $clientZip }}</p>
                    @endif
                    @if ($hasClient)
                        <a
                            href="https://www.google.com/maps?q={{ $clientLat }},{{ $clientLng }}"
                            target="_blank"
                            rel="noopener"
                            x-data
                            @click.prevent="window.open(/iPad|iPhone|iPod/.test(navigator.userAgent) ? 'https://maps.apple.com/?q={{ $clientLat }},{{ $clientLng }}' : $el.href, '_blank')"
                            class="ol-maps-link"
                        >
                            <x-heroicon-o-map-pin style="width: 0.875rem; height: 0.875rem;" />
                            Abrir no Maps
                        </a>
                    @endif
                @else
                    <p class="ol-text-italic">Não cadastrado</p>
                @endif
            </div>

            <div class="ol-card">
                <p class="ol-label">Endereço da Loja</p>
                @if ($storeAddressText)
                    <p class="ol-text">{{ $storeAddressText }}</p>
                    <p class="ol-text-muted">CEP: {{ $company->address_zip }}</p>
                    @if ($storeLat && $storeLng)
                        <a
                            href="https://www.google.com/maps?q={{ $storeLat }},{{ $storeLng }}"
                            target="_blank"
                            rel="noopener"
                            x-data
                            @click.prevent="window.open(/iPad|iPhone|iPod/.test(navigator.userAgent) ? 'https://maps.apple.com/?q={{ $storeLat }},{{ $storeLng }}' : $el.href, '_blank')"
                            class="ol-maps-link"
                        >
                            <x-heroicon-o-map-pin style="width: 0.875rem; height: 0.875rem;" />
                            Abrir no Maps
                        </a>
                    @endif
                @else
                    <p class="ol-text-italic">Não cadastrado</p>
                @endif
            </div>

            <div class="ol-card ol-card--distance">
                <p class="ol-label ol-label--primary">Distância</p>
                @if ($hasBoth)
                    <p class="ol-distance">
                        {{ number_format($distance, 2, ',', '.') }} km
                    </p>
                    <p class="ol-text-muted">em linha reta</p>
                @else
                    <p class="ol-text-italic">Coordenadas ausentes</p>
                @endif
            </div>
        </div>

        {{-- Mapa Leaflet --}}
        @if ($hasClient || $hasBoth)
            <div
                x-data="{
                    map: null,
                    clientLat: {{ $clientLat ?? 'null' }},
                    clientLng: {{ $clientLng ?? 'null' }},
                    storeLat: {{ $storeLat ?? 'null' }},
                    storeLng: {{ $storeLng ?? 'null' }},

                    initMap() {
                        if (this.map) return;

                        this.map = L.map(this.$refs.mapEl);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a>'
                        }).addTo(this.map);

                        var bounds = [];

                        if (this.storeLat && this.storeLng) {
                            var storeIcon = L.divIcon({
                                className: '',
                                html: '<div style=\'width:14px;height:14px;background:#3b82f6;border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)\'></div>',
                                iconAnchor: [7, 7]
                            });
                            L.marker([this.storeLat, this.storeLng], { icon: storeIcon })
                                .addTo(this.map)
                                .bindTooltip('{{ addslashes($company?->name ?? 'Loja') }}', { permanent: true, direction: 'top', offset: [0, -10] });
                            bounds.push([this.storeLat, this.storeLng]);
                        }

                        if (this.clientLat && this.clientLng) {
                            var clientIcon = L.divIcon({
                                className: '',
                                html: '<div style=\'width:14px;height:14px;background:#ef4444;border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)\'></div>',
                                iconAnchor: [7, 7]
                            });
                            L.marker([this.clientLat, this.clientLng], { icon: clientIcon })
                                .addTo(this.map)
                                .bindTooltip('{{ addslashes($addressLabel) }}', { permanent: true, direction: 'top', offset: [0, -10] });
                            bounds.push([this.clientLat, this.clientLng]);
                        }

                        if (this.storeLat && this.storeLng && this.clientLat && this.clientLng) {
                            L.polyline([[this.storeLat, this.storeLng], [this.clientLat, this.clientLng]], {
                                color: '#6366f1',
                                weight: 2,
                                dashArray: '6 4',
                                opacity: 0.7
                            }).addTo(this.map);
                        }

                        if (bounds.length > 1) {
                            this.map.fitBounds(bounds, { padding: [40, 40] });
                        } else if (bounds.length === 1) {
                            this.map.setView(bounds[0], 15);
                        }
                    },

                    loadLeaflet(cb) {
                        if (window.L) { cb(); return; }
                        if (!document.getElementById('leaflet-css')) {
                            var link = document.createElement('link');
                            link.id = 'leaflet-css';
                            link.rel = 'stylesheet';
                            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                            document.head.appendChild(link);
                        }
                        var existing = document.getElementById('leaflet-js');
                        if (existing) {
                            // Já está carregando em outro componente; espera terminar.
                            var waited = 0;
                            var waitInterval = setInterval(() => {
                                waited += 50;
                                if (window.L) {
                                    clearInterval(waitInterval);
                                    cb();
                                } else if (waited >= 5000) {
                                    // O carregamento anterior travou/falhou; remove e tenta de novo.
                                    clearInterval(waitInterval);
                                    existing.remove();
                                    this.loadLeaflet(cb);
                                }
                            }, 50);
                            return;
                        }
                        var script = document.createElement('script');
                        script.id = 'leaflet-js';
                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        script.onload = cb;
                        script.onerror = () => script.remove();
                        document.head.appendChild(script);
                    }
                }"
                x-init="loadLeaflet(() => $nextTick(() => initMap()))"
                wire:ignore
                x-ref="mapEl"
                style="height: 320px; border-radius: 0.75rem; overflow: hidden; border: 1px solid #e5e7eb; z-index: 0;"
            ></div>
        @else
            <div class="ol-empty-map">
                <p class="ol-text-italic">Cadastre o endereço do cliente e da loja para visualizar o mapa.</p>
            </div>
        @endif

    </div>
</div>
