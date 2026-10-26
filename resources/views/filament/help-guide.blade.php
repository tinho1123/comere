@php
    $tenant = filament()->getTenant();
@endphp
@if ($tenant)
    <style>
        /* Sem classes Tailwind cruas aqui — ver filament-admin-custom-views-missing-tailwind:
           o CSS compilado do Filament não inclui utilitários não usados pelo próprio pacote. */
        #comere-help-fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #6366f1;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            font-family: inherit;
            border: none;
            cursor: pointer;
            z-index: 9990;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.5);
        }

        #comere-help-panel {
            position: fixed;
            right: 24px;
            bottom: 92px;
            width: 340px;
            max-height: 70vh;
            overflow-y: auto;
            background: #1a1823;
            border: 1px solid #332e41;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            z-index: 9990;
            display: none;
            font-family: inherit;
            color: #e7e5e4;
        }

        #comere-help-panel.chg-open {
            display: block;
        }

        .chg-panel-header {
            padding: 16px 18px;
            border-bottom: 1px solid #2a2635;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chg-panel-title {
            font-size: 14px;
            font-weight: 700;
            color: #f5f5f4;
        }

        .chg-panel-close {
            background: transparent;
            border: none;
            color: #9ca3af;
            font-size: 16px;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
        }

        .chg-section-label {
            padding: 14px 18px 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #818cf8;
        }

        .chg-flow-list {
            padding: 0 10px 12px;
        }

        .chg-flow-item {
            padding: 10px 10px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            color: #e7e5e4;
            font-family: inherit;
        }

        .chg-flow-item:hover {
            background: #221f2b;
        }

        .chg-flow-item.chg-disabled {
            opacity: 0.45;
            cursor: default;
        }

        .chg-flow-item.chg-disabled:hover {
            background: transparent;
        }

        .chg-flow-item.chg-enabled {
            background: #1f2b1f;
        }

        .chg-flow-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #27212b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .chg-flow-text {
            flex: 1;
            min-width: 0;
        }

        .chg-flow-title {
            font-size: 13.5px;
            color: #e7e5e4;
        }

        .chg-flow-hint {
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .chg-flow-hint.chg-hint-ready {
            color: #86efac;
        }

        /* Spotlight: escurece tudo ao redor do elemento-alvo, sem bloquear cliques.
           z-index precisa ficar acima do card de alerta de pedido (#comere-order-alerts,
           z-index 9999 em pending-orders-poll.blade.php) — senão o card (opaco) é
           pintado por cima do anel amarelo e só a borda que escapa por fora do card
           fica visível, deixando a "linha amarela" aparentemente flutuando abaixo dele. */
        #comere-help-spotlight {
            position: fixed;
            z-index: 10000;
            border-radius: 10px;
            box-shadow: 0 0 0 4px #fbbf24, 0 0 0 9999px rgba(0, 0, 0, 0.55);
            pointer-events: none;
            display: none;
            transition: top 0.15s ease, left 0.15s ease, width 0.15s ease, height 0.15s ease;
        }

        #comere-help-tooltip {
            position: fixed;
            z-index: 10001;
            width: 300px;
            background: #1a1823;
            border: 1px solid #332e41;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            padding: 16px 18px;
            display: none;
            font-family: inherit;
            color: #e7e5e4;
        }

        .chg-tooltip-tail {
            position: absolute;
            width: 14px;
            height: 14px;
            background: #1a1823;
            border: 1px solid #332e41;
            transform: rotate(45deg);
        }

        .chg-tooltip-tail.chg-tail-top {
            top: -8px;
            border-bottom: none;
            border-right: none;
        }

        .chg-tooltip-tail.chg-tail-bottom {
            bottom: -8px;
            border-top: none;
            border-left: none;
        }

        .chg-tooltip-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .chg-tooltip-badge {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .chg-tooltip-kicker {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: #818cf8;
        }

        .chg-tooltip-text {
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 14px;
        }

        .chg-tooltip-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .chg-tooltip-step {
            font-size: 12px;
            color: #6b7280;
        }

        .chg-tooltip-actions {
            display: flex;
            gap: 8px;
        }

        .chg-btn {
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .chg-btn-ghost {
            background: transparent;
            color: #9ca3af;
        }

        .chg-btn-primary {
            background: #fbbf24;
            color: #1c1917;
        }
    </style>

    <button id="comere-help-fab" type="button" title="Ajuda">?</button>

    <div id="comere-help-panel">
        <div class="chg-panel-header">
            <div class="chg-panel-title">Com o que você precisa de ajuda?</div>
            <button type="button" class="chg-panel-close" id="comere-help-panel-close">✕</button>
        </div>
        <div class="chg-section-label">PASSO A PASSO</div>
        <div class="chg-flow-list" id="comere-help-static-flows"></div>
        <div class="chg-section-label">EM TEMPO REAL</div>
        <div class="chg-flow-list" id="comere-help-realtime-flows"></div>
    </div>

    <div id="comere-help-spotlight"></div>
    <div id="comere-help-tooltip"></div>

    <script>
        (function () {
            const STORAGE_KEY = 'comere_help_tour_v1';
            const companyUuid = @js($tenant->uuid);
            const countEndpoint = @js(route('admin.pending-orders.count'));
            const productsIndexUrl = @js(\App\Filament\Admin\Resources\ProductResource::getUrl('index'));
            const productsCreateUrl = @js(\App\Filament\Admin\Resources\ProductResource::getUrl('create'));
            const tablesIndexUrl = @js(\App\Filament\Admin\Resources\TableResource::getUrl('index'));
            const tablesCreateUrl = @js(\App\Filament\Admin\Resources\TableResource::getUrl('create'));
            const driversIndexUrl = @js(\App\Filament\Admin\Resources\DriverResource::getUrl('index'));
            const sessionViewUrlTemplate = @js(\App\Filament\Admin\Resources\TableSessionResource::getUrl('view', ['record' => '__UUID__']));
            const clientsCreateUrl = @js(\App\Filament\Admin\Resources\ClientResource::getUrl('create'));
            const favoredCreateUrl = @js(\App\Filament\Admin\Resources\FavoredTransactionResource::getUrl('create'));
            const couponsIndexUrl = @js(\App\Filament\Admin\Resources\CouponResource::getUrl('index'));
            const deliveryFeeRangesIndexUrl = @js(\App\Filament\Admin\Resources\DeliveryFeeRangeResource::getUrl('index'));
            const usersCreateUrl = @js(\App\Filament\Admin\Resources\UserResource::getUrl('create'));
            const tableSessionsIndexUrl = @js(\App\Filament\Admin\Resources\TableSessionResource::getUrl('index'));
            const salesReportUrl = @js(\App\Filament\Admin\Pages\SalesReportByPaymentMethod::getUrl());
            const suppliersIndexUrl = @js(\App\Filament\Admin\Resources\SupplierResource::getUrl('index'));
            const supplierExchangesIndexUrl = @js(\App\Filament\Admin\Resources\SupplierExchangeResource::getUrl('index'));
            const replenishmentSuggestionsUrl = @js(\App\Filament\Admin\Pages\ReplenishmentSuggestions::getUrl());

            const FLOWS = {
                'produto-criar': {
                    icon: '📦',
                    label: 'Criar um produto',
                    startUrl: productsCreateUrl,
                    steps: [
                        { title: 'Criar produto', text: 'Preencha o nome do produto e os demais campos do formulário.', selector: '[data-tour="produto-nome"]' },
                        { title: 'Criar produto', text: 'Quando terminar, role até o final da página e clique em "Criar" para salvar.', selector: null, final: true },
                    ],
                },
                'produto-editar': {
                    icon: '✏️',
                    label: 'Editar um produto',
                    startUrl: productsIndexUrl,
                    steps: [
                        { title: 'Editar produto', text: 'Clique em "Editar" na linha do produto que deseja alterar.', selector: '[data-tour="produto-editar"]' },
                        { title: 'Editar produto', text: 'Altere os campos desejados e clique em "Salvar" no rodapé da página.', selector: null, final: true },
                    ],
                },
                'mesa-criar-qr': {
                    icon: '🍽️',
                    label: 'Criar uma mesa e gerar QR Code',
                    startUrl: tablesCreateUrl,
                    steps: [
                        { title: 'Criar mesa', text: 'Dê um nome à mesa e clique em "Criar" no rodapé da página.', selector: '[data-tour="mesa-nome"]' },
                        { title: 'Gerar QR Code', text: 'De volta à lista, clique no menu "⋮" da mesa criada e escolha "QR Code" para gerar e baixar o código.', selector: null, final: true },
                    ],
                },
                'motorista-convidar': {
                    icon: '🚚',
                    label: 'Cadastrar motorista',
                    startUrl: driversIndexUrl,
                    steps: [
                        { title: 'Cadastrar motorista', text: 'Clique em "Convidar motorista" e informe o telefone dele (ele precisa já ter uma conta criada em /drivers/cadastro).', selector: '[data-tour="motorista-convidar"]', final: true },
                    ],
                },
                'cliente-criar': {
                    icon: '👤',
                    label: 'Cadastrar um cliente',
                    startUrl: clientsCreateUrl,
                    steps: [
                        { title: 'Cadastrar cliente', text: 'Preencha o nome e os demais dados do cliente.', selector: '[data-tour="cliente-nome"]' },
                        { title: 'Cadastrar cliente', text: 'Quando terminar, role até o final da página e clique em "Criar" para salvar.', selector: null, final: true },
                    ],
                },
                'fiado-lancar': {
                    icon: '💳',
                    label: 'Lançar um fiado',
                    startUrl: favoredCreateUrl,
                    steps: [
                        { title: 'Lançar fiado', text: 'Informe o nome de quem está devendo (não precisa ser um cliente cadastrado).', selector: '[data-tour="fiado-devedor"]' },
                        { title: 'Lançar fiado', text: 'Escolha os produtos e o vencimento e clique em "Criar" no rodapé da página.', selector: null, final: true },
                    ],
                },
                'cupom-criar': {
                    icon: '🏷️',
                    label: 'Cadastrar um cupom de desconto',
                    startUrl: couponsIndexUrl,
                    steps: [
                        { title: 'Cadastrar cupom', text: 'Clique em "Novo" para abrir o cadastro de cupom.', selector: '[data-tour="cupom-criar"]' },
                        { title: 'Cadastrar cupom', text: 'Defina o código e o valor do desconto e clique em "Criar".', selector: '[data-tour="cupom-codigo"]', final: true },
                    ],
                },
                'taxa-entrega-criar': {
                    icon: '🛵',
                    label: 'Configurar taxa de entrega',
                    startUrl: deliveryFeeRangesIndexUrl,
                    steps: [
                        { title: 'Taxa de entrega', text: 'Clique em "Novo" para cadastrar uma faixa de distância e sua taxa.', selector: '[data-tour="taxa-criar"]' },
                        { title: 'Taxa de entrega', text: 'Escolha a faixa (km) e o valor da taxa e clique em "Criar".', selector: '[data-tour="taxa-faixa"]', final: true },
                    ],
                },
                'usuario-criar': {
                    icon: '🔑',
                    label: 'Cadastrar um usuário administrador',
                    startUrl: usersCreateUrl,
                    steps: [
                        { title: 'Cadastrar usuário', text: 'Informe o e-mail do novo usuário administrador.', selector: '[data-tour="usuario-email"]' },
                        { title: 'Cadastrar usuário', text: 'Preencha o nome e a senha e clique em "Criar" no rodapé da página.', selector: null, final: true },
                    ],
                },
                'mesa-fechar': {
                    icon: '🧾',
                    label: 'Fechar uma mesa e registrar pagamento',
                    startUrl: tableSessionsIndexUrl,
                    steps: [
                        { title: 'Fechar mesa', text: 'Clique em "Fechar Mesa" na linha de uma mesa aberta para registrar a forma de pagamento.', selector: '[data-tour="mesa-fechar"]', final: true },
                    ],
                },
                'relatorio-vendas-ver': {
                    icon: '📊',
                    label: 'Ver relatório de vendas',
                    startUrl: salesReportUrl,
                    steps: [
                        { title: 'Relatório de vendas', text: 'Escolha aqui se quer ver um mês específico ou o ano completo.', selector: '[data-tour="relatorio-periodo"]' },
                        { title: 'Relatório de vendas', text: 'Clique em "Exportar CSV" para baixar os totais do período em uma planilha.', selector: '[data-tour="relatorio-export"]', final: true },
                    ],
                },
                'fornecedor-criar': {
                    icon: '🚚',
                    label: 'Cadastrar um fornecedor',
                    startUrl: suppliersIndexUrl,
                    steps: [
                        { title: 'Cadastrar fornecedor', text: 'Clique em "Novo" para abrir o cadastro de fornecedor.', selector: '[data-tour="fornecedor-criar"]' },
                        { title: 'Cadastrar fornecedor', text: 'Informe o nome, a frequência de visita e o prazo de entrega, depois clique em "Criar".', selector: null, final: true },
                    ],
                },
                'troca-fornecedor-criar': {
                    icon: '🔁',
                    label: 'Registrar troca com fornecedor',
                    startUrl: supplierExchangesIndexUrl,
                    steps: [
                        { title: 'Troca com fornecedor', text: 'Clique em "Novo" para registrar um produto com defeito de fabricação para troca.', selector: '[data-tour="troca-fornecedor-criar"]' },
                        { title: 'Troca com fornecedor', text: 'Escolha o produto, a quantidade e o motivo, depois clique em "Criar".', selector: null, final: true },
                    ],
                },
                'reposicao-ver': {
                    icon: '📋',
                    label: 'Ver sugestão de reposição',
                    startUrl: replenishmentSuggestionsUrl,
                    steps: [
                        { title: 'Sugestão de reposição', text: 'Aqui você vê, por fornecedor, quanto pedir de cada produto com base na venda média e na data da próxima visita. Associe um fornecedor aos produtos em Estoque → Produtos para aparecerem aqui.', selector: null, final: true },
                    ],
                },
                'pedido-aprovar': {
                    icon: '✅',
                    label: 'Aprovar um pedido',
                    realtime: true,
                    steps: [
                        { title: 'Aprovar pedido', text: 'Esse é o alerta do pedido que chegou. Clique em "✓ Aprovar" para confirmar.', selector: '#comere-order-alerts [data-action="approve"]', final: true },
                    ],
                },
                'mesa-entregar-item': {
                    icon: '🔔',
                    label: 'Entregar item de uma mesa',
                    realtime: true,
                    steps: [
                        { title: 'Entregar item', text: 'Clique em "Entregar" para marcar este item como entregue ao cliente.', selector: '[data-tour="mesa-entregar"]', final: true },
                    ],
                    start: function (ctx) {
                        if (!ctx.pendingSessionUuid) {
                            return false;
                        }
                        const url = sessionViewUrlTemplate.replace('__UUID__', ctx.pendingSessionUuid);
                        if (window.location.href.split('?')[0].replace(/\/$/, '') !== url.replace(/\/$/, '')) {
                            saveState('mesa-entregar-item', 0);
                            window.location.href = url;
                            return true;
                        }
                        return 'continue';
                    },
                },
            };

            function saveState(flowId, step) {
                try {
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ flowId: flowId, step: step }));
                } catch (e) { /* storage indisponível (modo privado) — tour simplesmente não persiste entre páginas */ }
            }

            function readState() {
                try {
                    const raw = sessionStorage.getItem(STORAGE_KEY);
                    return raw ? JSON.parse(raw) : null;
                } catch (e) {
                    return null;
                }
            }

            function clearTour() {
                try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) { /* ver readState() */ }
                stopTracking();
                hideSpotlight();
                hideTooltip();
            }

            const fab = document.getElementById('comere-help-fab');
            const panel = document.getElementById('comere-help-panel');
            const staticList = document.getElementById('comere-help-static-flows');
            const realtimeList = document.getElementById('comere-help-realtime-flows');
            const spotlight = document.getElementById('comere-help-spotlight');
            const tooltip = document.getElementById('comere-help-tooltip');

            let latestGating = { pendingOrders: 0, pendingSessionUuid: null };

            function renderFlowList() {
                staticList.innerHTML = '';
                realtimeList.innerHTML = '';

                Object.keys(FLOWS).forEach(function (flowId) {
                    const flow = FLOWS[flowId];
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'chg-flow-item';

                    let enabled = true;
                    let hint = '';
                    let hintReady = false;

                    if (flow.realtime) {
                        if (flowId === 'pedido-aprovar') {
                            enabled = latestGating.pendingOrders > 0;
                            hint = enabled
                                ? (latestGating.pendingOrders + ' pedido(s) pendente(s) agora')
                                : 'Disponível quando houver um pedido pendente';
                            hintReady = enabled;
                        } else if (flowId === 'mesa-entregar-item') {
                            enabled = !!latestGating.pendingSessionUuid;
                            hint = enabled ? 'Há um item pendente de entrega' : 'Disponível quando houver item pendente';
                            hintReady = enabled;
                        }
                    }

                    item.classList.add(enabled ? 'chg-enabled' : 'chg-disabled');
                    if (!flow.realtime) {
                        item.classList.remove('chg-enabled');
                    }

                    item.innerHTML = '<span class="chg-flow-icon">' + flow.icon + '</span>'
                        + '<span class="chg-flow-text"><span class="chg-flow-title">' + flow.label + '</span>'
                        + (hint ? '<div class="chg-flow-hint' + (hintReady ? ' chg-hint-ready' : '') + '">' + hint + '</div>' : '')
                        + '</span>';

                    if (enabled) {
                        item.addEventListener('click', function () {
                            closePanel();
                            startFlow(flowId);
                        });
                    }

                    (flow.realtime ? realtimeList : staticList).appendChild(item);
                });
            }

            function fetchGating() {
                return fetch(countEndpoint + '?company=' + companyUuid, { headers: { 'Accept': 'application/json' } })
                    .then(function (res) { return res.ok ? res.json() : null; })
                    .then(function (data) {
                        if (data) {
                            latestGating = {
                                pendingOrders: data.pending_orders_count || 0,
                                pendingSessionUuid: data.pending_session_uuid || null,
                            };
                        }
                    })
                    .catch(function () { /* mantém o último estado conhecido em caso de falha de rede */ });
            }

            function openPanel() {
                panel.classList.add('chg-open');
                fetchGating().then(renderFlowList);
                renderFlowList();
            }

            function closePanel() {
                panel.classList.remove('chg-open');
            }

            fab.addEventListener('click', function () {
                if (panel.classList.contains('chg-open')) {
                    closePanel();
                } else {
                    openPanel();
                }
            });
            document.getElementById('comere-help-panel-close').addEventListener('click', closePanel);

            function startFlow(flowId) {
                const flow = FLOWS[flowId];
                if (!flow) {
                    return;
                }
                if (typeof flow.start === 'function') {
                    const result = flow.start({ pendingSessionUuid: latestGating.pendingSessionUuid });
                    if (result === true) {
                        return; // start() já navegou e salvou o estado
                    }
                    if (result === false) {
                        return; // pré-condição não satisfeita (ex.: item deixou de estar pendente)
                    }
                } else if (flow.startUrl) {
                    const target = new URL(flow.startUrl, window.location.origin);
                    if (window.location.pathname !== target.pathname) {
                        saveState(flowId, 0);
                        window.location.href = flow.startUrl;
                        return;
                    }
                }
                saveState(flowId, 0);
                renderActiveStep();
            }

            function hideSpotlight() {
                spotlight.style.display = 'none';
            }

            function hideTooltip() {
                tooltip.style.display = 'none';
                tooltip.innerHTML = '';
            }

            let trackInterval = null;
            let currentTargetEl = null;
            let activeAdvanceHandler = null;

            function stopTracking() {
                if (trackInterval) {
                    clearInterval(trackInterval);
                    trackInterval = null;
                }
                if (activeAdvanceHandler) {
                    document.removeEventListener('click', activeAdvanceHandler, true);
                }
                activeAdvanceHandler = null;
                currentTargetEl = null;
            }

            function positionAround(targetEl) {
                const rect = targetEl.getBoundingClientRect();
                const pad = 6;

                spotlight.style.display = 'block';
                spotlight.style.top = (rect.top - pad) + 'px';
                spotlight.style.left = (rect.left - pad) + 'px';
                spotlight.style.width = (rect.width + pad * 2) + 'px';
                spotlight.style.height = (rect.height + pad * 2) + 'px';

                const tooltipWidth = 300;
                const spaceBelow = window.innerHeight - rect.bottom;
                const placement = spaceBelow > 180 ? 'bottom' : 'top';

                let left = rect.left + rect.width / 2 - tooltipWidth / 2;
                left = Math.max(16, Math.min(left, window.innerWidth - 16 - tooltipWidth));

                tooltip.style.display = 'block';
                tooltip.style.left = left + 'px';
                tooltip.style.width = tooltipWidth + 'px';

                if (placement === 'bottom') {
                    tooltip.style.top = (rect.bottom + 18) + 'px';
                } else {
                    // posicionado após medir a altura real do conteúdo
                    const h = tooltip.offsetHeight || 140;
                    tooltip.style.top = (rect.top - h - 18) + 'px';
                }

                const tail = tooltip.querySelector('.chg-tooltip-tail');
                if (tail) {
                    const tailLeft = Math.max(16, Math.min(rect.left + rect.width / 2 - left - 7, tooltipWidth - 30));
                    tail.style.left = tailLeft + 'px';
                }
            }

            function findVisible(selector) {
                // Alguns seletores (ex.: o botão "Aprovar" do card de pedido, que
                // existe duas vezes — versão cheia e versão minimizada) casam com
                // mais de um elemento ao mesmo tempo, um deles escondido via
                // display:none. querySelector() pegaria o primeiro do DOM mesmo
                // que escondido, e getBoundingClientRect() de um elemento
                // escondido retorna tudo zero — jogando a seta pro canto superior
                // esquerdo. Por isso sempre escolhe o primeiro que está
                // realmente visível.
                const candidates = document.querySelectorAll(selector);
                for (let i = 0; i < candidates.length; i++) {
                    if (candidates[i].getClientRects().length > 0) {
                        return candidates[i];
                    }
                }
                return null;
            }

            function waitForSelector(selector, timeoutMs, onFound, onTimeout) {
                const start = Date.now();
                (function poll() {
                    const el = findVisible(selector);
                    if (el) {
                        onFound(el);
                        return;
                    }
                    if (Date.now() - start > timeoutMs) {
                        onTimeout();
                        return;
                    }
                    setTimeout(poll, 200);
                })();
            }

            function renderActiveStep() {
                stopTracking();
                hideSpotlight();
                hideTooltip();

                const state = readState();
                if (!state) {
                    return;
                }
                const flow = FLOWS[state.flowId];
                if (!flow) {
                    clearTour();
                    return;
                }
                const step = flow.steps[state.step];
                if (!step) {
                    clearTour();
                    return;
                }

                const stepLabel = 'Passo ' + (state.step + 1) + ' de ' + flow.steps.length;

                if (!step.selector) {
                    renderTooltipBody(null, step, stepLabel, state);
                    centerTooltipNearFab();
                    return;
                }

                waitForSelector(step.selector, 10000, function (el) {
                    renderTooltipBody(el, step, stepLabel, state);
                    positionAround(el);
                    trackTarget(step.selector, flow, state);
                }, function () {
                    renderTooltipBody(null, step, stepLabel, state, true);
                    centerTooltipNearFab();
                });
            }

            // Delegado no document (fase de captura) em vez de vinculado a um
            // elemento específico: assim, quando o alvo visível troca (ex.:
            // minimizar/maximizar o card de pedido troca qual dos dois botões
            // "Aprovar" está na tela), o clique já é reconhecido na hora —
            // sem depender do polling de 300ms já ter rebindado o handler.
            function bindAdvance(selector, flow, state) {
                const handler = function (e) {
                    const matched = e.target.closest(selector);
                    if (!matched || matched.getClientRects().length === 0) {
                        return;
                    }
                    // Encerra o polling de 300ms já aqui, antes de qualquer
                    // setTimeout — senão, numa ação final rápida (ex.: o
                    // fetch de "Aprovar" removendo o card antes dos 50ms do
                    // clearTour), o intervalo ainda vivo pode medir o alvo
                    // já removido ou, numa corrida, reexibir o balão vazio
                    // (hideTooltip() já rodou, mas positionAround() do tick
                    // seguinte volta a mostrar display:block sem conteúdo).
                    stopTracking();
                    hideSpotlight();
                    hideTooltip();
                    if (isFinalStep(flow, state)) {
                        setTimeout(clearTour, 50);
                    } else {
                        saveState(state.flowId, state.step + 1);
                        // Nem toda ação navega pra outra página (ex.: CreateAction que
                        // abre um modal no lugar) — nesse caso não há livewire:navigated
                        // nem DOMContentLoaded pra retomar o tour, então também
                        // re-renderiza aqui. waitForSelector() já tolera o próximo
                        // passo ainda não existir no DOM (modal abrindo via Livewire).
                        setTimeout(renderActiveStep, 150);
                    }
                };
                document.addEventListener('click', handler, true);
                return handler;
            }

            // Reposiciona em volta do elemento real a cada 300ms (Livewire pode
            // reposicionar/trocar o layout) e também acompanha quando a versão
            // visível de um seletor muda — ex.: o card de pedido pendente tem um
            // botão "Aprovar" para a versão cheia e outro para a minimizada.
            function trackTarget(selector, flow, state) {
                currentTargetEl = findVisible(selector) || document.querySelector(selector);
                if (currentTargetEl) {
                    positionAround(currentTargetEl);
                }
                activeAdvanceHandler = bindAdvance(selector, flow, state);

                trackInterval = setInterval(function () {
                    const el = findVisible(selector);
                    if (!el || !document.body.contains(el)) {
                        stopTracking();
                        hideSpotlight();
                        hideTooltip();
                        return;
                    }
                    currentTargetEl = el;
                    positionAround(el);
                }, 300);
            }

            function isFinalStep(flow, state) {
                return !!flow.steps[state.step].final;
            }

            function renderTooltipBody(el, step, stepLabel, state, notFound) {
                const flow = FLOWS[state.flowId];
                const isLast = state.step === flow.steps.length - 1 || step.final;

                let footerRight = '';
                if (notFound && step.gotoUrl) {
                    footerRight = '<button type="button" class="chg-btn chg-btn-primary" id="chg-goto-btn">' + (step.gotoLabel || 'Ir até lá') + '</button>';
                } else if (!step.selector) {
                    footerRight = '<button type="button" class="chg-btn chg-btn-primary" id="chg-finish-btn">' + (isLast ? 'Concluir' : 'Entendi') + '</button>';
                }

                tooltip.innerHTML = ''
                    + '<div class="chg-tooltip-tail chg-tail-top"></div>'
                    + '<div class="chg-tooltip-head">'
                    + '<span class="chg-tooltip-badge">' + (state.step + 1) + '</span>'
                    + '<span class="chg-tooltip-kicker">' + step.title.toUpperCase() + '</span>'
                    + '</div>'
                    + '<div class="chg-tooltip-text">' + (notFound ? 'Não encontramos esse passo nesta tela. ' : '') + step.text + '</div>'
                    + '<div class="chg-tooltip-footer">'
                    + '<span class="chg-tooltip-step">' + stepLabel + '</span>'
                    + '<span class="chg-tooltip-actions">'
                    + '<button type="button" class="chg-btn chg-btn-ghost" id="chg-skip-btn">Fechar</button>'
                    + footerRight
                    + '</span>'
                    + '</div>';

                tooltip.style.display = 'block';

                document.getElementById('chg-skip-btn').addEventListener('click', clearTour);

                const gotoBtn = document.getElementById('chg-goto-btn');
                if (gotoBtn) {
                    gotoBtn.addEventListener('click', function () {
                        window.location.href = step.gotoUrl;
                    });
                }

                const finishBtn = document.getElementById('chg-finish-btn');
                if (finishBtn) {
                    finishBtn.addEventListener('click', function () {
                        if (isLast) {
                            clearTour();
                        } else {
                            saveState(state.flowId, state.step + 1);
                            renderActiveStep();
                        }
                    });
                }
            }

            function centerTooltipNearFab() {
                tooltip.style.left = (window.innerWidth - 24 - 300) + 'px';
                tooltip.style.top = (window.innerHeight - tooltip.offsetHeight - 92) + 'px';
                const tail = tooltip.querySelector('.chg-tooltip-tail');
                if (tail) {
                    tail.style.display = 'none';
                }
            }

            document.addEventListener('DOMContentLoaded', renderActiveStep);
            document.addEventListener('livewire:navigated', renderActiveStep);
            renderActiveStep();
        })();
    </script>
@endif
