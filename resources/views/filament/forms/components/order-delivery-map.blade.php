@php
    $lat = $getState();
    $lng = $getLng();
@endphp

@if ($lat && $lng)
    @php
        $latF = (float) $lat;
        $lngF = (float) $lng;
        $bbox = ($lngF - 0.005) . ',' . ($latF - 0.005) . ',' . ($lngF + 0.005) . ',' . ($latF + 0.005);
        $src = "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$latF},{$lngF}";
    @endphp

    <div class="w-full rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm" style="height: 280px;">
        <iframe
            src="{{ $src }}"
            style="width: 100%; height: 100%; border: 0;"
            loading="lazy"
            title="Localização de entrega"
        ></iframe>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
        Lat: {{ number_format($latF, 6) }} &nbsp;|&nbsp; Lng: {{ number_format($lngF, 6) }}
    </p>
@else
    <div class="flex items-center justify-center w-full rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800" style="height: 120px;">
        <p class="text-sm text-gray-400 dark:text-gray-500">
            Preencha o endereço e clique em <strong>Buscar no mapa</strong>
        </p>
    </div>
@endif
