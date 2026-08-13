<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrega #{{ strtoupper(substr($delivery->order->uuid, 0, 8)) }} — {{ $delivery->company->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { background: #f9fafb; font-family: system-ui, sans-serif; }
        .hidden { display: none !important; }
        .code-box { width: 46px; height: 54px; text-align: center; font-size: 22px; font-weight: 800; }
        #map { height: 260px; }
        .leaflet-div-icon { background: transparent; border: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-start py-6 px-4 pb-16">
<div class="w-full max-w-md">

    <div class="flex items-center gap-2 mb-4">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Pedido #{{ strtoupper(substr($delivery->order->uuid, 0, 8)) }}</p>
        <span class="text-gray-300">·</span>
        <p class="text-xs text-gray-400">{{ $delivery->company->name }}</p>
    </div>

    {{-- ===================== ESTÁGIO: RETIRADA NA LOJA ===================== --}}
    <div id="stage-pickup" class="hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
            <p class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-1">Retirada · loja</p>
            <h1 class="text-lg font-black text-gray-900">{{ $delivery->company->name }}</h1>
            <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                {{ $delivery->company->address_street }}, {{ $delivery->company->address_number }}
                @if ($delivery->company->address_complement)
                    — {{ $delivery->company->address_complement }}
                @endif
                <br>
                {{ $delivery->company->address_neighborhood }}, {{ $delivery->company->address_city }}/{{ $delivery->company->address_state }}
            </p>
            @if ($delivery->company->latitude && $delivery->company->longitude)
                <a
                    href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->company->latitude }},{{ $delivery->company->longitude }}"
                    target="_blank"
                    class="inline-flex items-center gap-1 mt-3 text-sm font-bold text-red-500"
                >
                    Abrir rota no Google Maps →
                </a>
            @endif
        </div>

        @if ($delivery->order->items->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">O que retirar</p>
                <div class="flex flex-col divide-y divide-gray-50">
                    @foreach ($delivery->order->items as $item)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span><span class="font-black text-red-500 mr-1">{{ $item->quantity }}×</span>{{ $item->product_name }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center justify-between pt-3 mt-1 border-t border-gray-100 text-sm font-bold">
                    <span>Total do pedido</span>
                    <span>R$ {{ number_format($delivery->order->total_amount, 2, ',', '.') }}</span>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Código de retirada da loja</p>
            <p class="text-xs text-gray-400 mb-4">Peça o código pro atendente antes de sair com o pedido.</p>
            <div class="flex gap-2 justify-center mb-2">
                <input type="text" inputmode="numeric" maxlength="1" class="code-box pickup-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
                <input type="text" inputmode="numeric" maxlength="1" class="code-box pickup-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
                <input type="text" inputmode="numeric" maxlength="1" class="code-box pickup-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
                <input type="text" inputmode="numeric" maxlength="1" class="code-box pickup-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
            </div>
            <p id="pickup-code-error" class="hidden text-xs text-red-500 font-medium text-center mt-1">Código incorreto. Confira com a loja.</p>
        </div>

        <button id="confirm-pickup-btn" disabled class="w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-xl transition-all mb-2">
            Confirmar retirada na loja
        </button>
    </div>

    {{-- ===================== ESTÁGIO: EM TRÂNSITO (MAPA) ===================== --}}
    <div id="stage-transit" class="hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div id="map"></div>
            <div class="p-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Entrega · cliente</p>
                <h1 class="text-lg font-black text-gray-900">{{ $delivery->order->client->name ?? 'Cliente' }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $delivery->order->delivery_street }}, {{ $delivery->order->delivery_number }}
                    @if ($delivery->order->delivery_complement)
                        — {{ $delivery->order->delivery_complement }}
                    @endif
                    <br>
                    {{ $delivery->order->delivery_neighborhood }}, {{ $delivery->order->delivery_city }}/{{ $delivery->order->delivery_state }}
                </p>
                <p id="transit-distance" class="text-xs font-bold text-gray-400 mt-2"></p>
            </div>
        </div>

        @if ($delivery->order->client?->phone)
            <div class="grid grid-cols-2 gap-2 mb-4">
                <a href="tel:{{ $delivery->order->client->phone }}" class="flex items-center justify-center gap-2 bg-white border border-gray-200 rounded-xl py-3 text-sm font-bold text-gray-700">
                    Ligar
                </a>
                <a href="https://wa.me/55{{ preg_replace('/\D/', '', $delivery->order->client->phone) }}" target="_blank" class="flex items-center justify-center gap-2 bg-white border border-gray-200 rounded-xl py-3 text-sm font-bold text-gray-700">
                    WhatsApp
                </a>
            </div>
        @endif

        <p class="text-xs text-gray-400 text-center px-4">
            A tela de entrega abre sozinha quando você chegar perto do endereço do cliente.
        </p>
    </div>

    {{-- ===================== ESTÁGIO: CHEGOU / PAGAMENTO ===================== --}}
    <div id="stage-arrived" class="hidden">
        <div class="bg-emerald-500 text-white rounded-2xl p-4 mb-4 flex items-center gap-3 shadow-lg shadow-emerald-500/20">
            <div>
                <p class="font-bold text-sm">Você chegou na área do cliente</p>
                <p class="text-xs text-white/80">Confirme a entrega abaixo.</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Entrega · cliente</p>
            <h1 class="text-lg font-black text-gray-900">{{ $delivery->order->client->name ?? 'Cliente' }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $delivery->order->delivery_street }}, {{ $delivery->order->delivery_number }}
                @if ($delivery->order->delivery_complement)
                    — {{ $delivery->order->delivery_complement }}
                @endif
            </p>
        </div>

        @if ($delivery->order->payment_method && $delivery->order->payment_method !== 'credit')
            <div id="payment-card" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Forma de pagamento</p>
                <p class="text-lg font-bold text-gray-900 mb-4">
                    {{ \App\Models\Order::paymentOptions()[$delivery->order->payment_method] ?? $delivery->order->payment_method }}
                </p>

                <div id="payment-pending">
                    <div class="text-center bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">Valor a cobrar do cliente</p>
                        <p class="text-2xl font-black text-gray-900 mt-1">R$ {{ number_format($delivery->order->total_amount, 2, ',', '.') }}</p>
                    </div>

                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Código de confirmação do cliente</p>
                    <p class="text-xs text-gray-400 mb-3">Peça pro cliente mostrar o código na tela de pedidos dele.</p>
                    <div class="flex gap-2 justify-center mb-2">
                        <input type="text" inputmode="numeric" maxlength="1" class="code-box payment-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
                        <input type="text" inputmode="numeric" maxlength="1" class="code-box payment-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
                        <input type="text" inputmode="numeric" maxlength="1" class="code-box payment-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
                        <input type="text" inputmode="numeric" maxlength="1" class="code-box payment-code-digit border border-gray-200 rounded-xl focus:border-red-500 outline-none">
                    </div>
                    <p id="payment-code-error" class="hidden text-xs text-red-500 font-medium text-center mt-1">Código incorreto. Confira com o cliente.</p>

                    <button id="confirm-payment-btn" disabled class="w-full bg-gray-200 text-gray-400 font-bold py-3.5 rounded-xl mt-4">
                        Confirmar recebimento do pagamento
                    </button>
                </div>

                <div id="payment-done" class="hidden flex items-center justify-center gap-2 text-emerald-600 font-bold text-sm bg-emerald-50 rounded-xl py-3">
                    ✓ Pagamento recebido
                </div>
            </div>
        @endif

        <button id="confirm-delivery-btn" disabled class="w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-xl transition-all mb-2">
            Confirmar entrega
        </button>
    </div>

    {{-- ===================== ESTÁGIO: ENTREGUE ===================== --}}
    <div id="stage-delivered" class="hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center mb-4">
            <div class="w-16 h-16 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <p class="text-lg font-black text-gray-900">Entrega concluída!</p>
            <p class="text-sm text-gray-500 mt-1">Você ganhou R$ {{ number_format($delivery->driver_fee, 2, ',', '.') }} nessa corrida.</p>
        </div>

        <div id="feedback-card" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center mb-4">
            <p class="text-sm font-bold text-gray-700 mb-3">Como foi a retirada em {{ $delivery->company->name }}?</p>
            <div class="flex gap-3 justify-center">
                <button class="feedback-btn w-14 h-14 rounded-2xl border border-gray-200 flex items-center justify-center text-gray-400" data-rating="good">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
                </button>
                <button class="feedback-btn w-14 h-14 rounded-2xl border border-gray-200 flex items-center justify-center text-gray-400" data-rating="bad">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/></svg>
                </button>
            </div>
            <p id="feedback-thanks" class="hidden text-xs font-bold text-emerald-600 mt-3">Valeu pelo feedback!</p>
        </div>

        <a href="{{ route('drivers.dashboard') }}" class="block w-full text-center bg-red-500 hover:bg-red-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 transition-all">
            Voltar ao painel
        </a>
    </div>

    {{-- ===================== ESTÁGIO: FALHOU ===================== --}}
    <div id="stage-failed" class="hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
            <p class="text-lg font-bold text-gray-900">Esta entrega foi reportada com problema</p>
            <p class="text-sm text-gray-500 mt-1">Não é mais necessário compartilhar localização.</p>
        </div>
    </div>

    {{-- ===================== RODAPÉ: REPORTAR PROBLEMA ===================== --}}
    <div id="report-section" class="hidden mt-2">
        <button id="report-toggle-btn" class="w-full flex items-center justify-center gap-2 text-sm font-bold text-gray-400 py-2">
            Reportar problema
        </button>
        <div id="report-form" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mt-2">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">O que aconteceu?</p>
            <select id="report-reason" class="w-full border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none mb-3">
                @foreach ($failureReasons as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button id="report-submit-btn" class="w-full bg-red-500 text-white font-bold py-3 rounded-xl text-sm">Enviar</button>
        </div>
    </div>

</div>

<script>
    const TOKEN = @json($delivery->tracking_token);
    const URLS = {
        location: @json(route('delivery.tracking.update-location', $delivery->tracking_token)),
        confirmPickup: @json(route('delivery.tracking.confirm-pickup', $delivery->tracking_token)),
        confirmPayment: @json(route('delivery.tracking.confirm-payment', $delivery->tracking_token)),
        confirmDelivery: @json(route('delivery.tracking.confirm-delivery', $delivery->tracking_token)),
        reportProblem: @json(route('delivery.tracking.report-problem', $delivery->tracking_token)),
        feedback: @json(route('delivery.tracking.feedback', $delivery->tracking_token)),
    };
    const DESTINATION = {
        lat: @json($delivery->order->delivery_latitude),
        lng: @json($delivery->order->delivery_longitude),
    };
    const ARRIVAL_RADIUS_METERS = 150;

    let state = {
        status: @json($delivery->status),
        pickedUp: @json($delivery->isPickedUp()),
        paymentRequired: @json((bool) ($delivery->order->payment_method && $delivery->order->payment_method !== 'credit')),
        paymentCollected: @json($delivery->payment_collected),
        arrived: false,
    };

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {}),
        }).then(async (res) => ({ ok: res.ok, status: res.status, data: await res.json().catch(() => ({})) }));
    }

    function haversineMeters(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const toRad = (v) => (v * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function currentStage() {
        if (state.status === 'delivered') return 'delivered';
        if (state.status === 'failed') return 'failed';
        if (!state.pickedUp) return 'pickup';
        if (!state.arrived) return 'transit';
        return 'arrived';
    }

    function render() {
        const stage = currentStage();
        ['pickup', 'transit', 'arrived', 'delivered', 'failed'].forEach((s) => {
            document.getElementById('stage-' + s).classList.toggle('hidden', s !== stage);
        });
        document.getElementById('report-section').classList.toggle('hidden', !['pickup', 'transit', 'arrived'].includes(stage));

        const confirmDeliveryBtn = document.getElementById('confirm-delivery-btn');
        if (confirmDeliveryBtn) {
            const ready = !state.paymentRequired || state.paymentCollected;
            confirmDeliveryBtn.disabled = !ready;
            confirmDeliveryBtn.className = ready
                ? 'w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 mb-2'
                : 'w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-xl transition-all mb-2';
        }

        if (stage === 'transit' && DESTINATION.lat && DESTINATION.lng) {
            initMapIfNeeded();
        }
    }

    // ---- Código de 4 dígitos: auto-avança e habilita o botão quando completo ----
    function wireCodeInputs(selector, onComplete) {
        const inputs = Array.from(document.querySelectorAll(selector));
        inputs.forEach((input, i) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '');
                if (input.value && i < inputs.length - 1) inputs[i + 1].focus();
                const code = inputs.map((el) => el.value).join('');
                onComplete(code.length === inputs.length ? code : null);
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && i > 0) inputs[i - 1].focus();
            });
        });
        return inputs;
    }

    wireCodeInputs('.pickup-code-digit', (code) => {
        const btn = document.getElementById('confirm-pickup-btn');
        btn.disabled = !code;
        btn.className = code
            ? 'w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 mb-2'
            : 'w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-xl transition-all mb-2';
        btn.dataset.code = code || '';
    });

    document.getElementById('confirm-pickup-btn').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const code = btn.dataset.code;
        if (!code) return;
        btn.disabled = true;
        const { ok, data } = await post(URLS.confirmPickup, { code });
        if (ok && data.success) {
            state.pickedUp = true;
            document.getElementById('pickup-code-error').classList.add('hidden');
            render();
        } else {
            document.getElementById('pickup-code-error').classList.remove('hidden');
            btn.disabled = false;
        }
    });

    if (document.querySelector('.payment-code-digit')) {
        wireCodeInputs('.payment-code-digit', (code) => {
            const btn = document.getElementById('confirm-payment-btn');
            btn.disabled = !code;
            btn.className = code
                ? 'w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3.5 rounded-xl mt-4'
                : 'w-full bg-gray-200 text-gray-400 font-bold py-3.5 rounded-xl mt-4';
            btn.dataset.code = code || '';
        });

        document.getElementById('confirm-payment-btn').addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const code = btn.dataset.code;
            if (!code) return;
            btn.disabled = true;
            const { ok, data } = await post(URLS.confirmPayment, { code });
            if (ok && data.success) {
                document.getElementById('payment-pending').classList.add('hidden');
                document.getElementById('payment-done').classList.remove('hidden');
                state.paymentCollected = true;
                render();
            } else {
                document.getElementById('payment-code-error').classList.remove('hidden');
                btn.disabled = false;
            }
        });
    }

    document.getElementById('confirm-delivery-btn').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        if (btn.disabled) return;
        btn.disabled = true;
        const { ok, data } = await post(URLS.confirmDelivery);
        if (ok && data.success) {
            state.status = 'delivered';
            render();
        } else {
            btn.disabled = false;
            alert((data && data.message) || 'Não foi possível concluir a entrega.');
        }
    });

    document.querySelectorAll('.feedback-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            document.querySelectorAll('.feedback-btn').forEach((b) => b.classList.remove('bg-emerald-50', 'border-emerald-500', 'text-emerald-600'));
            btn.classList.add('bg-emerald-50', 'border-emerald-500', 'text-emerald-600');
            const { ok } = await post(URLS.feedback, { rating: btn.dataset.rating });
            if (ok) document.getElementById('feedback-thanks').classList.remove('hidden');
        });
    });

    document.getElementById('report-toggle-btn')?.addEventListener('click', () => {
        document.getElementById('report-form').classList.toggle('hidden');
    });

    document.getElementById('report-submit-btn')?.addEventListener('click', async () => {
        const reason = document.getElementById('report-reason').value;
        if (!confirm('Tem certeza? Isso encerra a entrega como não concluída.')) return;
        const { ok } = await post(URLS.reportProblem, { reason });
        if (ok) {
            state.status = 'failed';
            render();
        }
    });

    // ---- Mapa em trânsito + rastreio ao vivo ----
    let map, riderMarker, destMarker, geofenceCircle, watchId;
    let lastSentAt = 0;
    const MIN_INTERVAL_MS = 15000;

    function initMapIfNeeded() {
        if (map) return;
        map = L.map('map', { zoomControl: false, attributionControl: false }).setView([DESTINATION.lat, DESTINATION.lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

        destMarker = L.marker([DESTINATION.lat, DESTINATION.lng], {
            icon: L.divIcon({ className: '', html: '<div style="width:16px;height:16px;background:#ef4444;border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>', iconAnchor: [8, 8] }),
        }).addTo(map);

        geofenceCircle = L.circle([DESTINATION.lat, DESTINATION.lng], {
            radius: ARRIVAL_RADIUS_METERS,
            color: '#ef4444',
            fillColor: '#ef4444',
            fillOpacity: 0.1,
            weight: 2,
            dashArray: '6 4',
        }).addTo(map);
    }

    function updateRiderPosition(lat, lng) {
        if (!map) return;
        if (!riderMarker) {
            riderMarker = L.marker([lat, lng], {
                icon: L.divIcon({ className: '', html: '<div style="width:16px;height:16px;background:#10b981;border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>', iconAnchor: [8, 8] }),
            }).addTo(map);
        } else {
            riderMarker.setLatLng([lat, lng]);
        }
        map.fitBounds([[lat, lng], [DESTINATION.lat, DESTINATION.lng]], { padding: [30, 30] });
    }

    function handlePosition(position) {
        const { latitude, longitude } = position.coords;

        if (currentStage() === 'transit') {
            updateRiderPosition(latitude, longitude);

            if (DESTINATION.lat && DESTINATION.lng) {
                const distance = haversineMeters(latitude, longitude, DESTINATION.lat, DESTINATION.lng);
                document.getElementById('transit-distance').textContent = distance >= 1000
                    ? (distance / 1000).toFixed(1) + ' km do endereço'
                    : Math.round(distance) + ' m do endereço';

                if (distance <= ARRIVAL_RADIUS_METERS && !state.arrived) {
                    state.arrived = true;
                    render();
                }
            }
        }

        const now = Date.now();
        if (now - lastSentAt >= MIN_INTERVAL_MS && currentStage() !== 'delivered' && currentStage() !== 'failed') {
            lastSentAt = now;
            post(URLS.location, { latitude, longitude });
        }
    }

    if (navigator.geolocation) {
        watchId = navigator.geolocation.watchPosition(handlePosition, () => {}, {
            enableHighAccuracy: true,
            maximumAge: 10000,
            timeout: 20000,
        });
    }

    window.addEventListener('beforeunload', () => {
        if (watchId !== null && navigator.geolocation) navigator.geolocation.clearWatch(watchId);
    });

    render();
</script>
</body>
</html>
