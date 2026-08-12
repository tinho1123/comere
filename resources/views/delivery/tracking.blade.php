<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrega #{{ strtoupper(substr($delivery->order->uuid, 0, 8)) }} — {{ $delivery->company->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background: #f8fafc; font-family: system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-start py-8 px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
            <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider">{{ $delivery->company->name }}</p>
            <h1 class="text-xl font-bold text-gray-900 mt-1">Pedido #{{ strtoupper(substr($delivery->order->uuid, 0, 8)) }}</h1>
            <p class="text-sm text-gray-500 mt-1">Motorista: {{ $delivery->driver->name }}</p>

            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Endereço de entrega</p>
                <p class="text-sm text-gray-700 leading-relaxed">
                    {{ $delivery->order->delivery_street }}, {{ $delivery->order->delivery_number }}
                    @if ($delivery->order->delivery_complement)
                        — {{ $delivery->order->delivery_complement }}
                    @endif
                    <br>
                    {{ $delivery->order->delivery_neighborhood }}, {{ $delivery->order->delivery_city }}/{{ $delivery->order->delivery_state }}
                </p>
                @if ($delivery->order->delivery_latitude && $delivery->order->delivery_longitude)
                    <a
                        href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->order->delivery_latitude }},{{ $delivery->order->delivery_longitude }}"
                        target="_blank"
                        class="inline-flex items-center gap-1 mt-3 text-sm font-bold text-red-500"
                    >
                        Abrir rota no Google Maps →
                    </a>
                @endif
            </div>
        </div>

        @if ($delivery->order->payment_method && $delivery->order->payment_method !== 'credit')
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4 text-center">
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Forma de pagamento</p>
                <p class="text-lg font-bold text-gray-900 mb-4">
                    {{ \App\Models\Order::paymentOptions()[$delivery->order->payment_method] ?? $delivery->order->payment_method }}
                </p>

                @if ($delivery->payment_collected)
                    <div class="flex items-center justify-center gap-2 text-emerald-600 font-bold text-sm bg-emerald-50 rounded-xl py-3">
                        ✓ Pagamento recebido
                    </div>
                @elseif ($delivery->status === \App\Models\Delivery::STATUS_DISPATCHED)
                    <button
                        id="confirm-payment-btn"
                        onclick="confirmPayment()"
                        class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-emerald-500/20 transition-all active:scale-95"
                    >
                        Confirmar recebimento do pagamento
                    </button>
                @endif
            </div>
        @endif

        @if ($delivery->status !== \App\Models\Delivery::STATUS_DISPATCHED)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                <p class="text-lg font-bold text-gray-900">Esta entrega já foi finalizada</p>
                <p class="text-sm text-gray-500 mt-1">Não é mais necessário compartilhar localização.</p>
            </div>
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                <div id="idle-state">
                    <p class="text-sm text-gray-500 mb-4">
                        Toque no botão abaixo para compartilhar sua localização em tempo real com o cliente até a entrega ser concluída.
                    </p>
                    <button
                        id="start-btn"
                        onclick="startTracking()"
                        class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95"
                    >
                        Iniciar rastreio
                    </button>
                </div>

                <div id="active-state" class="hidden">
                    <div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse mx-auto mb-3"></div>
                    <p class="text-sm font-bold text-emerald-600">Rastreio ativo</p>
                    <p id="last-update" class="text-xs text-gray-400 mt-1">Enviando localização...</p>
                </div>

                <div id="error-state" class="hidden">
                    <p class="text-sm font-bold text-red-500">Não foi possível acessar sua localização.</p>
                    <p class="text-xs text-gray-400 mt-1">Verifique se o GPS está ativado e a permissão de localização foi concedida ao navegador.</p>
                </div>
            </div>
        @endif
    </div>

    <script>
        const TOKEN = @json($delivery->tracking_token);
        const UPDATE_URL = @json(route('delivery.tracking.update-location', $delivery->tracking_token));
        const CONFIRM_PAYMENT_URL = @json(route('delivery.tracking.confirm-payment', $delivery->tracking_token));
        let watchId = null;
        let lastSentAt = 0;
        const MIN_INTERVAL_MS = 15000;

        function confirmPayment() {
            const btn = document.getElementById('confirm-payment-btn');
            if (!btn) return;
            btn.disabled = true;
            btn.textContent = 'Confirmando...';

            fetch(CONFIRM_PAYMENT_URL, { method: 'POST' })
                .then((res) => res.json())
                .then((data) => {
                    if (data.success) {
                        btn.outerHTML = '<div class="flex items-center justify-center gap-2 text-emerald-600 font-bold text-sm bg-emerald-50 rounded-xl py-3">✓ Pagamento recebido</div>';
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Confirmar recebimento do pagamento';
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = 'Confirmar recebimento do pagamento';
                });
        }

        function sendLocation(position) {
            const now = Date.now();
            if (now - lastSentAt < MIN_INTERVAL_MS) return;
            lastSentAt = now;

            fetch(UPDATE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                }),
            }).then((res) => {
                if (res.status === 409) {
                    stopTracking();
                    document.getElementById('active-state').classList.add('hidden');
                    document.getElementById('idle-state').classList.remove('hidden');
                    return;
                }
                document.getElementById('last-update').textContent =
                    'Última atualização: ' + new Date().toLocaleTimeString('pt-BR');
            }).catch(() => {});
        }

        function stopTracking() {
            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
        }

        function startTracking() {
            if (!navigator.geolocation) {
                document.getElementById('idle-state').classList.add('hidden');
                document.getElementById('error-state').classList.remove('hidden');
                return;
            }

            watchId = navigator.geolocation.watchPosition(
                (position) => {
                    document.getElementById('idle-state').classList.add('hidden');
                    document.getElementById('error-state').classList.add('hidden');
                    document.getElementById('active-state').classList.remove('hidden');
                    sendLocation(position);
                },
                () => {
                    document.getElementById('idle-state').classList.add('hidden');
                    document.getElementById('error-state').classList.remove('hidden');
                },
                { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 }
            );
        }

        window.addEventListener('beforeunload', stopTracking);
    </script>
</body>
</html>
