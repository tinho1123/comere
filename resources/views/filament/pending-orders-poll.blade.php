@php
    $tenant = filament()->getTenant();

    // Deep link usado pelo "Abrir pedido" do card de alerta (?pedido=<uuid>)
    // e pela notificação em segundo plano — resolvido aqui (e não no
    // mount() da página) porque mountTableAction() só pode ser chamado
    // depois que a tabela do Livewire já foi montada; chamá-lo direto no
    // mount() da página dá erro fatal ("$table must not be accessed
    // before initialization"). Em vez disso, disparamos um evento do
    // Livewire (Livewire.dispatch) depois que a página carrega — o
    // listener #[On('comere-open-order')] em ManageOrders chama
    // mountTableAction() dentro do próprio componente correto. Chamar
    // Livewire.first() diretamente é frágil: pode resolver para outro
    // componente Livewire presente na mesma página (ex.: widgets),
    // que não tem esse método.
    $autoOpenOrderKey = null;
    if ($tenant && ($pedidoUuid = request()->query('pedido'))) {
        $autoOpenOrderKey = \App\Models\Order::where('company_id', $tenant->id)
            ->where('uuid', $pedidoUuid)
            ->value('id');
    }
@endphp
@if ($autoOpenOrderKey)
    <script>
        document.addEventListener('livewire:init', () => {
            window.addEventListener('load', () => {
                Livewire.dispatch('comere-open-order', { recordId: @js((string) $autoOpenOrderKey) });
            });
        });
    </script>
@endif
@if ($tenant)
    <style>
        /* Classes utilitárias Tailwind não existem no CSS compilado do painel
           (que só publica o que o Filament usa internamente) — por isso este
           componente usa apenas regras escopadas sob .coa-*, nunca classes
           como "flex" ou "rounded-xl" (mesmo padrão de order-location.blade.php). */
        #comere-order-alerts {
            /* Shrink-to-fit (nunca "right: 0") para que o retângulo do
               container corresponda à pilha de cards visível — arrastar
               precisa medir a posição real, não uma barra invisível do
               tamanho da viewport inteira. Centralizado por padrão via
               left: 50% + transform; ao arrastar, o JS troca para
               left/top em px e remove o transform. */
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            pointer-events: none;
        }
        .coa-drag-handle {
            pointer-events: auto;
            align-self: center;
            width: clamp(320px, 92vw, 560px);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            padding: 0.125rem;
            margin-bottom: -0.25rem;
            cursor: grab;
            color: #9ca3af;
            font-size: 0.625rem;
            letter-spacing: 0.05em;
            touch-action: none;
            user-select: none;
        }
        .coa-drag-handle:active {
            cursor: grabbing;
        }
        .coa-drag-handle-dots {
            font-size: 0.875rem;
            line-height: 1;
            opacity: 0.6;
        }
        .coa-drag-handle:hover .coa-drag-handle-dots {
            opacity: 1;
        }
        .coa-card {
            pointer-events: auto;
            width: clamp(320px, 92vw, 560px);
            background: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
            border: 1px solid #e5e7eb;
            border-top: 3px solid #4f46e5;
            overflow: hidden;
            animation: coa-slide-in 0.25s ease-out;
            font-family: inherit;
        }
        html.dark .coa-card {
            background: #1f2937;
            border-color: #374151;
        }
        @keyframes coa-slide-in {
            from { transform: translateY(-16px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .coa-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            cursor: pointer;
            user-select: none;
        }
        .coa-header-title {
            flex: 1;
            font-size: 0.8125rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        html.dark .coa-header-title {
            color: #f3f4f6;
        }
        .coa-pulse {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: #f97316;
            flex-shrink: 0;
            animation: coa-pulse 1.4s ease-in-out infinite;
        }
        @keyframes coa-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.55); }
            50% { box-shadow: 0 0 0 5px rgba(249, 115, 22, 0); }
        }
        .coa-time {
            font-size: 0.6875rem;
            color: #9ca3af;
            font-weight: 500;
        }
        .coa-toggle {
            border: none;
            background: transparent;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            border-radius: 0.375rem;
        }
        .coa-toggle:hover {
            background: #f3f4f6;
        }
        html.dark .coa-toggle:hover {
            background: #374151;
        }
        .coa-body {
            padding: 0 0.875rem 0.875rem;
        }
        .coa-card.coa-minimized .coa-body {
            display: none;
        }
        .coa-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .coa-client {
            font-size: 0.8125rem;
            color: #1f2937;
            font-weight: 600;
            margin: 0;
        }
        html.dark .coa-client {
            color: #e5e7eb;
        }
        .coa-total {
            font-size: 1.0625rem;
            font-weight: 700;
            color: #4f46e5;
            margin: 0;
            white-space: nowrap;
        }
        html.dark .coa-total {
            color: #a5b4fc;
        }
        .coa-items {
            max-height: 90px;
            overflow-y: auto;
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 0.5rem 0.625rem;
            margin-bottom: 0.5rem;
        }
        html.dark .coa-items {
            background: rgba(255, 255, 255, 0.04);
        }
        .coa-item-line {
            font-size: 0.75rem;
            color: #4b5563;
            display: flex;
            gap: 0.375rem;
            line-height: 1.4;
        }
        html.dark .coa-item-line {
            color: #d1d5db;
        }
        .coa-item-qty {
            font-weight: 700;
            color: #4f46e5;
            flex-shrink: 0;
        }
        html.dark .coa-item-qty {
            color: #a5b4fc;
        }
        .coa-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            margin-bottom: 0.75rem;
        }
        .coa-chip {
            font-size: 0.6875rem;
            font-weight: 600;
            color: #4338ca;
            background: #eef2ff;
            border-radius: 9999px;
            padding: 0.25rem 0.625rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        html.dark .coa-chip {
            color: #c7d2fe;
            background: rgba(99, 102, 241, 0.16);
        }
        .coa-chip--payment {
            color: #92400e;
            background: #fef3c7;
        }
        html.dark .coa-chip--payment {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.16);
        }
        .coa-actions {
            display: flex;
            gap: 0.5rem;
        }
        .coa-btn {
            flex: 1;
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            transition: opacity 0.15s;
        }
        .coa-btn:disabled {
            opacity: 0.6;
            cursor: default;
        }
        .coa-btn--approve {
            background: #059669;
            color: #fff;
        }
        .coa-btn--reject {
            background: #fff;
            color: #e11d48;
            border: 1px solid #fecdd3;
        }
        html.dark .coa-btn--reject {
            background: transparent;
            color: #fb7185;
            border-color: rgba(251, 113, 133, 0.35);
        }
        .coa-btn--open {
            background: #fff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
            flex: 0 0 auto;
            padding: 0.5rem 0.75rem;
        }
        html.dark .coa-btn--open {
            background: transparent;
            color: #a5b4fc;
            border-color: rgba(99, 102, 241, 0.35);
        }
        /* Minimizado: tira a pílula */
        .coa-mini-bar {
            display: none;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
        }
        .coa-card.coa-minimized .coa-mini-bar {
            display: flex;
        }
        .coa-card.coa-minimized .coa-header {
            display: none;
        }
        .coa-mini-label {
            flex: 1;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1f2937;
        }
        html.dark .coa-mini-label {
            color: #e5e7eb;
        }
        .coa-mini-actions {
            display: flex;
            gap: 0.375rem;
        }
        .coa-mini-btn {
            border: none;
            border-radius: 0.375rem;
            width: 1.75rem;
            height: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .coa-mini-btn--approve {
            background: #059669;
            color: #fff;
        }
        .coa-mini-btn--reject {
            background: #fde8e8;
            color: #e11d48;
        }
        html.dark .coa-mini-btn--reject {
            background: rgba(225, 29, 72, 0.18);
            color: #fb7185;
        }
        /* Card de mesa: mesmo visual do card de marketplace, mas sem
           aprovar/recusar — só um link pra tela da mesa — então usa uma
           cor de destaque diferente (laranja, igual ao toast que
           substitui) pra não parecer o mesmo tipo de alerta. */
        .coa-card--mesa {
            border-top-color: #f97316;
        }
        .coa-btn--mesa-open {
            background: #f97316;
            color: #fff;
        }
        .coa-mini-btn--mesa-open {
            background: #f97316;
            color: #fff;
            width: auto;
            padding: 0 0.625rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
    </style>

    <div id="comere-order-alerts"></div>

    <script>
        (function () {
            const companyUuid = @js($tenant->uuid);
            const mesaEndpoint = @js(route('admin.pending-orders.mesa'));
            const marketplaceEndpoint = @js(route('admin.pending-orders.marketplace'));
            const ordersIndexUrl = @js(\App\Filament\Admin\Resources\OrderResource::getUrl('index'));
            const csrfToken = @js(csrf_token());
            const approveUrl = (uuid) => @js(url('/admin/pedidos-pendentes')) + '/' + uuid + '/aprovar';
            const rejectUrl = (uuid) => @js(url('/admin/pedidos-pendentes')) + '/' + uuid + '/recusar';

            let marketplaceInitialized = false;
            let mesaInitialized = false;
            const renderedOrderIds = new Set();
            const renderedMesaSessionIds = new Set();
            const mesaLatestItemIds = new Map();
            const POSITION_KEY = 'comere_order_alerts_position';
            let swRegistration = null;

            function totalAlertCount() {
                return renderedOrderIds.size + renderedMesaSessionIds.size;
            }

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then((reg) => {
                    swRegistration = reg;
                }).catch(() => {});

                // Quando Aprovar/Recusar é clicado direto na notificação do
                // sistema (aba em segundo plano), o service worker avisa a
                // página pra atualizar a lista na hora, sem esperar o poll.
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data?.type === 'comere-order-resolved') {
                        checkMarketplaceOrders();
                    }
                });
            }

            function notifyNewOrderInBackground(order) {
                // Só manda notificação do sistema quando a aba não está em
                // primeiro plano (minimizada/outra aba/outra janela) — com
                // a aba visível o card já aparece na tela, sem precisar
                // disso. Usa o mesmo service worker do push para poder
                // mostrar os botões de ação (Aprovar/Recusar).
                if (document.visibilityState === 'visible' && document.hasFocus()) return;
                if (!swRegistration || typeof Notification === 'undefined' || Notification.permission !== 'granted') return;

                const deepLink = `${ordersIndexUrl}?pedido=${encodeURIComponent(order.uuid)}`;

                swRegistration.showNotification('🛒 Novo pedido no marketplace', {
                    body: `${order.client_name || 'Cliente'} · ${order.items_count} ${order.items_count === 1 ? 'item' : 'itens'} · ${formatMoney(order.total_amount)}`,
                    icon: '/icons/icon-192x192.png',
                    badge: '/icons/icon-72x72.png',
                    tag: `comere-order-${order.id}`,
                    requireInteraction: true,
                    vibrate: [200, 100, 200],
                    data: {
                        type: 'comere-order-alert',
                        url: deepLink,
                        orderUuid: order.uuid,
                        approveUrl: order.approve_url,
                        rejectUrl: order.reject_url,
                    },
                    actions: [
                        { action: 'approve', title: '✓ Aprovar' },
                        { action: 'reject', title: '✕ Recusar' },
                    ],
                }).catch(() => {});
            }

            function clamp(value, min, max) {
                return Math.min(Math.max(value, min), max);
            }

            function applySavedPosition() {
                const container = document.getElementById('comere-order-alerts');
                let saved = null;
                try {
                    saved = JSON.parse(localStorage.getItem(POSITION_KEY));
                } catch (e) {}
                if (!saved || typeof saved.left !== 'number' || typeof saved.top !== 'number') return;

                container.style.left = saved.left + 'px';
                container.style.top = saved.top + 'px';
                container.style.transform = 'none';
            }

            function clampContainerToViewport() {
                const container = document.getElementById('comere-order-alerts');
                // Só se aplica quando o container já foi tirado da posição
                // centralizada padrão (left: 50%) — ou seja, quando já tem
                // uma posição arrastada/salva em px pra reconferir.
                if (!container.style.left || container.style.left.endsWith('%')) return;

                const rect = container.getBoundingClientRect();
                if (rect.width === 0 && rect.height === 0) return;

                const maxLeft = Math.max(0, window.innerWidth - rect.width);
                const maxTop = Math.max(0, window.innerHeight - rect.height);
                const clampedLeft = clamp(rect.left, 0, maxLeft);
                const clampedTop = clamp(rect.top, 0, maxTop);

                if (clampedLeft === rect.left && clampedTop === rect.top) return;

                container.style.left = clampedLeft + 'px';
                container.style.top = clampedTop + 'px';

                try {
                    localStorage.setItem(POSITION_KEY, JSON.stringify({ left: clampedLeft, top: clampedTop }));
                } catch (e) {}
            }

            function initDragHandle(handle) {
                const container = document.getElementById('comere-order-alerts');
                let dragging = false;
                let startX = 0;
                let startY = 0;
                let startLeft = 0;
                let startTop = 0;

                handle.addEventListener('pointerdown', (event) => {
                    dragging = true;
                    handle.setPointerCapture(event.pointerId);

                    const rect = container.getBoundingClientRect();
                    container.style.left = rect.left + 'px';
                    container.style.top = rect.top + 'px';
                    container.style.transform = 'none';

                    startX = event.clientX;
                    startY = event.clientY;
                    startLeft = rect.left;
                    startTop = rect.top;
                    event.preventDefault();
                });

                handle.addEventListener('pointermove', (event) => {
                    if (!dragging) return;

                    const rect = container.getBoundingClientRect();
                    const maxLeft = Math.max(0, window.innerWidth - rect.width);
                    const maxTop = Math.max(0, window.innerHeight - rect.height);

                    const newLeft = clamp(startLeft + (event.clientX - startX), 0, maxLeft);
                    const newTop = clamp(startTop + (event.clientY - startY), 0, maxTop);

                    container.style.left = newLeft + 'px';
                    container.style.top = newTop + 'px';
                });

                handle.addEventListener('pointerup', (event) => {
                    if (!dragging) return;
                    dragging = false;
                    handle.releasePointerCapture(event.pointerId);

                    const rect = container.getBoundingClientRect();
                    try {
                        localStorage.setItem(POSITION_KEY, JSON.stringify({ left: rect.left, top: rect.top }));
                    } catch (e) {}
                });
            }

            function ensureDragHandle(container) {
                if (container.querySelector('.coa-drag-handle')) return;

                const handle = document.createElement('div');
                handle.className = 'coa-drag-handle';
                handle.title = 'Arraste para mover — a posição é lembrada para os próximos pedidos';
                handle.innerHTML = '<span class="coa-drag-handle-dots">⠿⠿⠿</span>';
                container.insertBefore(handle, container.firstChild);
                initDragHandle(handle);
            }

            // Chamado sempre que um card é removido na hora (aprovado/recusado
            // direto no card, sem esperar o próximo poll) — a alça de arrastar
            // não pode continuar visível sozinha, sem nenhum pedido pendente.
            function removeDragHandleIfEmpty() {
                if (totalAlertCount() > 0) return;
                document.getElementById('comere-order-alerts')?.querySelector('.coa-drag-handle')?.remove();
            }

            function beep() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = 880;
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.4);
                } catch (e) {}
            }

            function showToast(message) {
                const toast = document.createElement('div');
                toast.textContent = message;
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#f97316;color:#fff;padding:12px 20px;border-radius:8px;font-family:sans-serif;font-size:14px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.3);max-width:320px;';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 6000);
            }

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value ?? '';
                return div.innerHTML;
            }

            function formatMoney(value) {
                return 'R$ ' + Number(value || 0).toFixed(2).replace('.', ',');
            }

            function timeAgoLabel(isoDate) {
                const diffMs = Date.now() - new Date(isoDate).getTime();
                const minutes = Math.max(0, Math.floor(diffMs / 60000));
                if (minutes < 1) return 'agora mesmo';
                if (minutes === 1) return 'há 1 minuto';
                if (minutes < 60) return `há ${minutes} minutos`;
                const hours = Math.floor(minutes / 60);
                return hours === 1 ? 'há 1 hora' : `há ${hours} horas`;
            }

            function minimizedKey(orderId) {
                return `comere_order_alert_minimized_${orderId}`;
            }

            function buildCard(order) {
                const card = document.createElement('div');
                card.className = 'coa-card';
                card.dataset.orderId = String(order.id);
                card.dataset.createdAt = order.created_at;

                const isMinimized = localStorage.getItem(minimizedKey(order.id)) === '1';
                if (isMinimized) card.classList.add('coa-minimized');

                const itemsHtml = order.items.map((item) => `
                    <div class="coa-item-line">
                        <span class="coa-item-qty">${item.quantity}x</span>
                        <span>${escapeHtml(item.name)}</span>
                    </div>
                `).join('');

                const addressChip = order.address
                    ? `<span class="coa-chip">📍 ${escapeHtml(order.neighborhood || order.address)}</span>`
                    : '<span class="coa-chip">📍 Endereço não informado</span>';

                const distanceChip = order.distance_km !== null
                    ? `<span class="coa-chip">📏 ${Number(order.distance_km).toFixed(1).replace('.', ',')} km</span>`
                    : '';

                const paymentChip = `<span class="coa-chip coa-chip--payment">💳 ${escapeHtml(order.payment_method_label)}</span>`;

                card.innerHTML = `
                    <div class="coa-header" data-action="toggle">
                        <span class="coa-pulse"></span>
                        <span class="coa-header-title">
                            🛒 Novo pedido no marketplace
                            <span class="coa-time">· ${timeAgoLabel(order.created_at)}</span>
                        </span>
                        <button type="button" class="coa-toggle" data-action="toggle" title="Minimizar">▾</button>
                    </div>
                    <div class="coa-body">
                        <div class="coa-row">
                            <p class="coa-client">${escapeHtml(order.client_name || 'Cliente')} · ${order.items_count} ${order.items_count === 1 ? 'item' : 'itens'}</p>
                            <p class="coa-total">${formatMoney(order.total_amount)}</p>
                        </div>
                        <div class="coa-items">${itemsHtml}</div>
                        <div class="coa-meta">
                            ${addressChip}
                            ${distanceChip}
                            ${paymentChip}
                        </div>
                        <div class="coa-actions">
                            <button type="button" class="coa-btn coa-btn--approve" data-action="approve">✓ Aprovar</button>
                            <button type="button" class="coa-btn coa-btn--reject" data-action="reject">✕ Recusar</button>
                            <button type="button" class="coa-btn--open" data-action="open" title="Abrir pedido">↗</button>
                        </div>
                    </div>
                    <div class="coa-mini-bar">
                        <span class="coa-pulse"></span>
                        <span class="coa-mini-label">${escapeHtml(order.client_name || 'Cliente')} · ${formatMoney(order.total_amount)}</span>
                        <div class="coa-mini-actions">
                            <button type="button" class="coa-mini-btn coa-mini-btn--approve" data-action="approve" title="Aprovar">✓</button>
                            <button type="button" class="coa-mini-btn coa-mini-btn--reject" data-action="reject" title="Recusar">✕</button>
                            <button type="button" class="coa-mini-btn" data-action="toggle" title="Expandir">▴</button>
                        </div>
                    </div>
                `;

                card.addEventListener('click', (event) => {
                    const action = event.target.closest('[data-action]')?.dataset.action;
                    if (!action) return;

                    if (action === 'toggle') {
                        const minimized = card.classList.toggle('coa-minimized');
                        localStorage.setItem(minimizedKey(order.id), minimized ? '1' : '0');
                        return;
                    }

                    if (action === 'open') {
                        window.open(`${ordersIndexUrl}?pedido=${encodeURIComponent(order.uuid)}`, '_blank');
                        return;
                    }

                    if (action === 'approve' || action === 'reject') {
                        const buttons = card.querySelectorAll('button');
                        buttons.forEach((btn) => (btn.disabled = true));

                        const endpoint = action === 'approve' ? approveUrl(order.uuid) : rejectUrl(order.uuid);

                        fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        }).then((res) => {
                            if (res.status === 409) {
                                // O pedido já mudou de status em outra tela (aprovado/
                                // despachado) enquanto este card estava com dados
                                // defasados — recarrega a lista para descartá-lo.
                                showToast('⚠️ Este pedido já foi atualizado em outra tela.');
                                checkMarketplaceOrders();
                                return;
                            }
                            if (!res.ok) throw new Error('request failed');
                            localStorage.removeItem(minimizedKey(order.id));
                            renderedOrderIds.delete(order.id);
                            card.remove();
                            removeDragHandleIfEmpty();
                        }).catch(() => {
                            buttons.forEach((btn) => (btn.disabled = false));
                        });
                    }
                });

                return card;
            }

            async function checkMarketplaceOrders() {
                try {
                    const res = await fetch(marketplaceEndpoint + '?company=' + companyUuid, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!res.ok) return;

                    const data = await res.json();
                    const orders = data.orders || [];
                    const container = document.getElementById('comere-order-alerts');
                    const currentIds = new Set(orders.map((o) => o.id));

                    // Remove cards for orders that are no longer pending (aprovado/recusado
                    // por este card ou pela tela de Pedidos/Sessões).
                    renderedOrderIds.forEach((id) => {
                        if (!currentIds.has(id)) {
                            const el = container.querySelector(`[data-order-id="${id}"]`);
                            if (el) el.remove();
                            renderedOrderIds.delete(id);
                            localStorage.removeItem(minimizedKey(id));
                        }
                    });

                    orders.forEach((order) => {
                        if (renderedOrderIds.has(order.id)) return;

                        renderedOrderIds.add(order.id);
                        container.appendChild(buildCard(order));

                        // No primeiro carregamento da página, pedidos pendentes só
                        // reaparecem na tela (sem som) — o alerta sonoro é só para
                        // pedidos genuinamente novos durante a sessão.
                        if (marketplaceInitialized) {
                            beep();
                            notifyNewOrderInBackground(order);
                        }
                    });

                    // A alça de arrastar só aparece quando há algo pra mover —
                    // a posição arrastada já foi salva e continua valendo para
                    // os próximos cards (ensureDragHandle não mexe na posição).
                    if (totalAlertCount() > 0) {
                        ensureDragHandle(container);
                        // Reconfere a cada atualização (não só na primeira vez) —
                        // uma posição salva num monitor maior pode cair fora da
                        // viewport atual (janela menor, celular, etc.).
                        clampContainerToViewport();
                    } else {
                        container.querySelector('.coa-drag-handle')?.remove();
                    }

                    marketplaceInitialized = true;
                } catch (e) {}
            }

            function mesaMinimizedKey(sessionId) {
                return `comere_mesa_alert_minimized_${sessionId}`;
            }

            function renderMesaCardBody(card, session) {
                card.dataset.createdAt = session.created_at;

                const itemsHtml = session.items.map((item) => `
                    <div class="coa-item-line">
                        <span class="coa-item-qty">${item.quantity}x</span>
                        <span>${escapeHtml(item.name)}</span>
                    </div>
                `).join('');

                const guestLabel = session.guest_name
                    ? `${escapeHtml(session.table_name)} · ${escapeHtml(session.guest_name)}`
                    : escapeHtml(session.table_name);

                card.innerHTML = `
                    <div class="coa-header" data-action="toggle">
                        <span class="coa-pulse"></span>
                        <span class="coa-header-title">
                            🍽️ Novo pedido na mesa
                            <span class="coa-time">· ${timeAgoLabel(session.created_at)}</span>
                        </span>
                        <button type="button" class="coa-toggle" data-action="toggle" title="Minimizar">▾</button>
                    </div>
                    <div class="coa-body">
                        <div class="coa-row">
                            <p class="coa-client">${guestLabel} · ${session.items_count} ${session.items_count === 1 ? 'item' : 'itens'}</p>
                        </div>
                        <div class="coa-items">${itemsHtml}</div>
                        <div class="coa-actions">
                            <button type="button" class="coa-btn coa-btn--mesa-open" data-action="open-mesa">➔ Ir para a mesa</button>
                        </div>
                    </div>
                    <div class="coa-mini-bar">
                        <span class="coa-pulse"></span>
                        <span class="coa-mini-label">${guestLabel}</span>
                        <div class="coa-mini-actions">
                            <button type="button" class="coa-mini-btn coa-mini-btn--mesa-open" data-action="open-mesa" title="Ir para a mesa">Ir</button>
                            <button type="button" class="coa-mini-btn" data-action="toggle" title="Expandir">▴</button>
                        </div>
                    </div>
                `;
            }

            function buildMesaCard(session) {
                const card = document.createElement('div');
                card.className = 'coa-card coa-card--mesa';
                card.dataset.mesaSessionId = String(session.id);

                const isMinimized = localStorage.getItem(mesaMinimizedKey(session.id)) === '1';
                if (isMinimized) card.classList.add('coa-minimized');

                renderMesaCardBody(card, session);

                card.addEventListener('click', (event) => {
                    const action = event.target.closest('[data-action]')?.dataset.action;
                    if (!action) return;

                    if (action === 'toggle') {
                        const minimized = card.classList.toggle('coa-minimized');
                        localStorage.setItem(mesaMinimizedKey(session.id), minimized ? '1' : '0');
                        return;
                    }

                    if (action === 'open-mesa') {
                        window.location.href = session.url;
                    }
                });

                return card;
            }

            async function checkMesaOrders() {
                try {
                    const res = await fetch(mesaEndpoint + '?company=' + companyUuid, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!res.ok) return;

                    const data = await res.json();
                    const sessions = data.sessions || [];
                    const container = document.getElementById('comere-order-alerts');
                    const currentIds = new Set(sessions.map((s) => s.id));

                    // Remove cards for sessions that no longer have a pending item
                    // (entregue ou mesa fechada).
                    renderedMesaSessionIds.forEach((id) => {
                        if (!currentIds.has(id)) {
                            const el = container.querySelector(`[data-mesa-session-id="${id}"]`);
                            if (el) el.remove();
                            renderedMesaSessionIds.delete(id);
                            mesaLatestItemIds.delete(id);
                            localStorage.removeItem(mesaMinimizedKey(id));
                        }
                    });

                    sessions.forEach((session) => {
                        if (renderedMesaSessionIds.has(session.id)) {
                            // Mesa já tem card aberto — atualiza o conteúdo (pode ter
                            // chegado mais um item antes do anterior ser entregue) e
                            // só bipa se o item mais recente realmente mudou.
                            const previousLatestItemId = mesaLatestItemIds.get(session.id) ?? null;
                            if (
                                session.latest_item_id !== null
                                && previousLatestItemId !== null
                                && session.latest_item_id > previousLatestItemId
                            ) {
                                beep();
                            }
                            mesaLatestItemIds.set(session.id, session.latest_item_id);

                            const card = container.querySelector(`[data-mesa-session-id="${session.id}"]`);
                            if (card) renderMesaCardBody(card, session);
                            return;
                        }

                        renderedMesaSessionIds.add(session.id);
                        mesaLatestItemIds.set(session.id, session.latest_item_id);
                        container.appendChild(buildMesaCard(session));

                        if (mesaInitialized) {
                            beep();
                        }
                    });

                    if (totalAlertCount() > 0) {
                        ensureDragHandle(container);
                        clampContainerToViewport();
                    } else {
                        container.querySelector('.coa-drag-handle')?.remove();
                    }

                    mesaInitialized = true;
                } catch (e) {}
            }

            function updateCardTimes() {
                document.querySelectorAll('#comere-order-alerts .coa-card').forEach((card) => {
                    const timeEl = card.querySelector('.coa-time');
                    if (timeEl && card.dataset.createdAt) {
                        timeEl.textContent = '· ' + timeAgoLabel(card.dataset.createdAt);
                    }
                });
            }

            applySavedPosition();
            window.addEventListener('resize', clampContainerToViewport);
            checkMesaOrders();
            checkMarketplaceOrders();
            setInterval(checkMesaOrders, 8000);
            setInterval(checkMarketplaceOrders, 8000);
            setInterval(updateCardTimes, 15000);
        })();
    </script>
@endif
