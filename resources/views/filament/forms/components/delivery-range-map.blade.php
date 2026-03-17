@php
    $company = \Filament\Facades\Filament::getTenant();
    $lat     = $company?->latitude  ? (float) $company->latitude  : null;
    $lng     = $company?->longitude ? (float) $company->longitude : null;
    $hasCoords = $lat && $lng;
@endphp

<div
    x-data="{
        map: null,
        circle: null,
        lat: {{ $hasCoords ? $lat : 'null' }},
        lng: {{ $hasCoords ? $lng : 'null' }},
        km: null,

        initMap() {
            if (!this.lat || !this.lng) return;
            if (this.map) return;

            this.map = L.map(this.$refs.mapEl, {
                dragging: false,
                touchZoom: false,
                doubleClickZoom: false,
                scrollWheelZoom: false,
                boxZoom: false,
                keyboard: false,
                zoomControl: false,
                attributionControl: true
            }).setView([this.lat, this.lng], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(this.map);

            L.marker([this.lat, this.lng], {
                icon: L.divIcon({
                    className: '',
                    html: '<div style=\'width:14px;height:14px;background:#3b82f6;border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)\'></div>',
                    iconAnchor: [7, 7]
                })
            }).addTo(this.map).bindTooltip('{{ addslashes($company?->name ?? 'Loja') }}', { permanent: true, direction: 'top', offset: [0, -10] });
        },

        updateCircle() {
            if (!this.map || !this.km) return;
            if (this.circle) { this.circle.remove(); this.circle = null; }

            this.map.invalidateSize();

            this.circle = L.circle([this.lat, this.lng], {
                radius: this.km * 1000,
                color: '#ef4444',
                fillColor: '#ef4444',
                fillOpacity: 0.12,
                weight: 2,
                dashArray: '6 4'
            }).addTo(this.map);

            this.map.fitBounds(this.circle.getBounds().pad(0.2));
        },

        loadLeaflet(cb) {
            if (window.L) { cb(); return; }
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = cb;
            document.head.appendChild(script);
        }
    }"
    x-init="
        loadLeaflet(() => {
            $nextTick(() => {
                initMap();
                const initialKm = parseInt($wire.data.max_km) || null;
                if (initialKm) {
                    km = initialKm;
                    updateCircle();
                }
                $wire.$watch('data.max_km', v => {
                    km = parseInt(v) || null;
                    updateCircle();
                });
            });
        });
    "
>
    @if ($hasCoords)
        <div x-ref="mapEl" style="height: 280px; border-radius: 0.75rem; overflow: hidden; border: 1px solid #e5e7eb;"></div>
        <p class="mt-1.5 text-xs text-gray-400">
            Selecione uma faixa acima para visualizar a área de cobertura em vermelho.
        </p>
    @else
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"
            style="height: 160px;"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-gray-300 mb-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
            <p class="text-sm text-gray-400">Cadastre o endereço da loja no painel master para visualizar o mapa</p>
        </div>
    @endif
</div>
