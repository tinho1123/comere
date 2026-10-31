@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $dataUrl = route('admin.orders-kanban.data', ['company' => $tenant->uuid]);
    $exitUrl = \App\Filament\Admin\Pages\Dashboard::getUrl(tenant: $tenant);
    $tutorialKey = 'comere_orders_kanban_tutorial_seen_'.$tenant->uuid;
@endphp

<x-filament-panels::page>
    {{-- Classes utilitárias do Tailwind cru não existem no CSS compilado do painel
         (ver filament-admin-custom-views-missing-tailwind) — tudo aqui é CSS
         inline/escopado em .okp-*, sem depender do build do Filament. --}}
    <style>
        .okp-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: #0f0f12;
            color: #f4f4f5;
            font-family: inherit;
            display: flex;
            flex-direction: column;
            padding: 24px 32px 20px;
            gap: 18px;
            overflow: hidden;
        }
        .okp-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .okp-title { margin: 0; font-size: 22px; font-weight: 700; color: #fff; }
        .okp-subtitle { margin: 0; font-size: 13px; color: #a1a1aa; }
        .okp-live { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #34d399; background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.3); border-radius: 999px; padding: 6px 12px; }
        .okp-dot { width: 7px; height: 7px; border-radius: 50%; background: #34d399; }
        .okp-exit-btn { display: flex; align-items: center; gap: 6px; background: #1c1c20; border: 1px solid #2a2a30; color: #d4d4d8; border-radius: 10px; padding: 8px 14px; font-size: 13px; cursor: pointer; }
        .okp-board { display: flex; gap: 20px; overflow-x: auto; flex: 1 1 auto; padding-bottom: 8px; align-items: flex-start; }
        .okp-column { flex: 0 0 300px; display: flex; flex-direction: column; gap: 10px; max-height: 100%; }
        .okp-column-head { display: flex; align-items: center; justify-content: space-between; padding: 0 2px; }
        .okp-column-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: #fff; }
        .okp-column-dot { width: 10px; height: 10px; border-radius: 3px; }
        .okp-column-count { font-size: 12px; font-weight: 700; border-radius: 999px; padding: 2px 9px; }
        .okp-column-body { display: flex; flex-direction: column; gap: 10px; overflow-y: auto; padding-right: 4px; }
        .okp-column-footer { font-size: 11px; color: #71717a; padding: 0 2px; }
        .okp-card { background: #1c1c20; border: 1px solid #2a2a30; border-left: 3px solid #3f3f46; border-radius: 10px; padding: 12px; display: flex; flex-direction: column; gap: 6px; cursor: pointer; position: relative; }
        .okp-card:hover { border-color: #3f3f46; }
        .okp-badge { font-size: 10px; font-weight: 700; border-radius: 6px; padding: 3px 7px; align-self: flex-start; }
        .okp-card-title { font-size: 14px; font-weight: 700; color: #fff; }
        .okp-card-sub { font-size: 12px; color: #d4d4d8; }
        .okp-card-row { display: flex; justify-content: space-between; align-items: center; }
        .okp-total { font-size: 15px; font-weight: 700; color: #fff; }
        .okp-tag { font-size: 10px; border-radius: 6px; padding: 4px 8px; display: inline-flex; align-items: center; gap: 4px; }
        .okp-progress-bar { height: 5px; border-radius: 4px; background: #2a2a30; overflow: hidden; }
        .okp-progress-fill { height: 100%; background: #818cf8; }
        .okp-empty { text-align: center; font-size: 12px; color: #52525b; padding: 16px 0; }

        @keyframes okp-blink-danger {
            0%, 100% { box-shadow: 0 0 0 0 rgba(244,63,94,0.55); border-left-color: #f43f5e; }
            50% { box-shadow: 0 0 0 6px rgba(244,63,94,0); border-left-color: #fda4af; }
        }
        .okp-alert-approval { animation: okp-blink-danger 1.3s ease-in-out infinite; }
        @keyframes okp-blink-msg {
            0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.65); }
            50% { box-shadow: 0 0 0 6px rgba(59,130,246,0); }
        }
        .okp-alert-message-dot { animation: okp-blink-msg 1.3s ease-in-out infinite; position: absolute; top: -8px; right: -8px; width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 2px solid #0f0f12; }

        .okp-tutorial-spotlight { position: fixed; z-index: 1100; border-radius: 14px; border: 2px solid #f43f5e; box-shadow: 0 0 0 3000px rgba(0,0,0,0.72); pointer-events: none; transition: all 0.25s ease; }
        .okp-tutorial-tip { position: fixed; z-index: 1101; width: 300px; background: #18181c; border: 1px solid #3f3f46; border-radius: 12px; padding: 16px; box-shadow: 0 12px 32px rgba(0,0,0,0.5); display: flex; flex-direction: column; gap: 10px; transition: all 0.25s ease; }
        .okp-tutorial-step-badge { width: 22px; height: 22px; border-radius: 50%; background: #f43f5e; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .okp-tutorial-skip { background: transparent; border: none; color: #a1a1aa; font-size: 12px; cursor: pointer; }
        .okp-tutorial-next { background: #f43f5e; border: none; color: #fff; border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 700; cursor: pointer; }

        .okp-rotate-overlay { display: none; }
        @media (max-width: 900px) and (orientation: portrait) {
            .okp-board, .okp-header, .okp-overlay > div:last-child { display: none; }
            .okp-rotate-overlay {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 18px;
                text-align: center;
                flex: 1 1 auto;
                padding: 32px;
            }
        }
        @keyframes okp-rotate-hint { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(90deg); } }
        .okp-rotate-icon { font-size: 52px; display: inline-block; animation: okp-rotate-hint 1.8s ease-in-out infinite; }
    </style>

    <div class="okp-overlay" id="okp-root" data-exit-url="{{ $exitUrl }}">
        <div class="okp-rotate-overlay">
            <span class="okp-rotate-icon">📱</span>
            <div style="font-size: 18px; font-weight: 700; color: #ffffff;">Gire o celular para continuar</div>
            <p style="margin: 0; font-size: 13px; color: #a1a1aa; max-width: 280px; line-height: 1.5;">
                O painel de pedidos só funciona na horizontal, para mostrar todas as etapas lado a lado.
            </p>
            <button type="button" class="okp-exit-btn" onclick="window.location.href = document.getElementById('okp-root').dataset.exitUrl;">✕ Sair</button>
        </div>
        <div class="okp-header">
            <div>
                <h1 class="okp-title">Painel de Pedidos</h1>
                <p class="okp-subtitle">Marketplace e Mesas, nas mesmas etapas &middot; atualiza a cada 10s</p>
            </div>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <div class="okp-live"><span class="okp-dot"></span> Ao vivo</div>
                <button type="button" class="okp-exit-btn" id="okp-exit-btn">✕ Sair <span style="color:#71717a;">(Esc)</span></button>
            </div>
        </div>

        <div class="okp-board" id="okp-board">
            <div class="okp-column" data-column="pendente">
                <div class="okp-column-head">
                    <div class="okp-column-title"><span class="okp-column-dot" style="background:#f43f5e;"></span> Pendente</div>
                    <span class="okp-column-count" style="color:#f43f5e; background:rgba(244,63,94,0.12);" data-count></span>
                </div>
                <div class="okp-column-body" data-body></div>
                <div class="okp-column-footer" data-footer></div>
            </div>
            <div class="okp-column" data-column="em_preparo">
                <div class="okp-column-head">
                    <div class="okp-column-title"><span class="okp-column-dot" style="background:#f59e0b;"></span> Em preparo</div>
                    <span class="okp-column-count" style="color:#f59e0b; background:rgba(245,158,11,0.12);" data-count></span>
                </div>
                <div class="okp-column-body" data-body></div>
                <div class="okp-column-footer" data-footer></div>
            </div>
            <div class="okp-column" data-column="pronto">
                <div class="okp-column-head">
                    <div class="okp-column-title"><span class="okp-column-dot" style="background:#818cf8;"></span> Pronto / Em entrega</div>
                    <span class="okp-column-count" style="color:#818cf8; background:rgba(99,102,241,0.14);" data-count></span>
                </div>
                <div class="okp-column-body" data-body></div>
                <div class="okp-column-footer" data-footer></div>
            </div>
            <div class="okp-column" data-column="entregue">
                <div class="okp-column-head">
                    <div class="okp-column-title"><span class="okp-column-dot" style="background:#34d399;"></span> Entregue / Fechada</div>
                    <span class="okp-column-count" style="color:#34d399; background:rgba(16,185,129,0.12);" data-count></span>
                </div>
                <div class="okp-column-body" data-body></div>
            </div>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:18px; font-size:12px; color:#71717a; border-top:1px solid #1f1f23; padding-top:12px;">
            <span>🛍 Marketplace = pedidos do site/app</span>
            <span>🍽 Mesa = sessões do salão (auto-aprovadas)</span>
            <span style="color:#fda4af;">🔔 pisca vermelho = precisa de aprovação</span>
            <span style="color:#93c5fd;">💬 pisca azul = mensagem nova do cliente</span>
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('okp-root');
            if (!root) return;

            const dataUrl = @js($dataUrl);
            const exitUrl = root.dataset.exitUrl;
            const tutorialKey = @js($tutorialKey);

            const columnMeta = {
                pendente: { color: '#f43f5e' },
                em_preparo: { color: '#f59e0b' },
                pronto: { color: '#818cf8' },
                entregue: { color: '#34d399' },
            };

            let pollTimer = null;
            const seenApprovalIds = new Set();
            const seenMessageIds = new Set();
            let firstLoad = true;

            function exit() {
                window.location.href = exitUrl;
            }

            document.getElementById('okp-exit-btn').addEventListener('click', exit);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') exit();
            });

            function beep(frequency) {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.frequency.value = frequency;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    gain.gain.setValueAtTime(0.08, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.35);
                } catch (e) { /* som é um extra, nunca deve travar o painel */ }
            }

            function money(value) {
                return 'R$ ' + Number(value || 0).toFixed(2).replace('.', ',');
            }

            function timeAgo(iso) {
                const diffMin = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
                if (diffMin < 1) return 'agora';
                if (diffMin < 60) return 'há ' + diffMin + ' min';
                return 'há ' + Math.round(diffMin / 60) + 'h';
            }

            function orderCard(card, columnColor) {
                const el = document.createElement('div');
                el.className = 'okp-card' + (card.needs_approval ? ' okp-alert-approval' : '');
                el.style.borderLeftColor = columnColor;

                let html = '<span class="okp-badge" style="color:#fb7185; background:rgba(244,63,94,0.12);">🛍 '
                    + (card.channel === 'online' ? 'MARKETPLACE' : 'BALCÃO') + '</span>';
                if (card.unread_messages > 0) {
                    html += '<span class="okp-alert-message-dot">💬</span>';
                }
                html += '<div class="okp-card-title">Pedido ' + card.label + '</div>';
                html += '<div class="okp-card-sub">' + card.customer + ' &middot; ' + card.items_count + ' itens &middot; ' + timeAgo(card.created_at) + '</div>';
                if (card.needs_approval) {
                    html += '<span class="okp-tag" style="color:#fda4af; background:rgba(244,63,94,0.14); font-weight:700;">🔔 Precisa de aprovação</span>';
                }
                if (card.unread_messages > 0) {
                    html += '<span class="okp-tag" style="color:#93c5fd; background:rgba(59,130,246,0.12);">💬 ' + card.unread_messages + ' mensagem(ns) nova(s)</span>';
                }
                if (card.driver_name) {
                    html += '<span class="okp-tag" style="color:#a5b4fc; background:rgba(99,102,241,0.1);">🏍 Em rota &middot; ' + card.driver_name + '</span>';
                }
                if (card.delivery_failed) {
                    html += '<span class="okp-tag" style="color:#fca5a5; background:rgba(244,63,94,0.12);">⚠️ Entrega falhou</span>';
                }
                html += '<div class="okp-card-row"><span class="okp-total">' + money(card.total) + '</span>'
                    + '<span style="font-size:11px; color:#a1a1aa; border:1px solid #2a2a30; border-radius:6px; padding:2px 6px;">' + (card.payment_method || '—') + '</span></div>';

                el.innerHTML = html;
                el.addEventListener('click', function () { window.location.href = card.url; });
                return el;
            }

            function sessionCard(card, columnColor, columnKey) {
                const el = document.createElement('div');
                el.className = 'okp-card';
                el.style.borderLeftColor = columnColor;

                let html = '<span class="okp-badge" style="color:#a5b4fc; background:rgba(99,102,241,0.14);">🍽 ' + card.label.toUpperCase() + '</span>';
                html += '<div class="okp-card-title">' + card.customer + '</div>';

                if (columnKey === 'pendente') {
                    html += '<span class="okp-tag" style="color:#a1a1aa; background:rgba(161,161,170,0.1);">✅ Aprovação automática de mesa</span>';
                } else if (columnKey === 'entregue') {
                    html += '<span class="okp-tag" style="color:#d4d4d8; background:rgba(161,161,170,0.1);">Conta fechada</span>';
                } else {
                    const pct = card.items_total > 0 ? Math.round((card.items_delivered / card.items_total) * 100) : 0;
                    html += '<div style="display:flex; justify-content:space-between; font-size:11px; color:#a1a1aa;">'
                        + '<span>' + card.items_delivered + ' de ' + card.items_total + ' servidos</span><span>' + pct + '%</span></div>';
                    html += '<div class="okp-progress-bar"><div class="okp-progress-fill" style="width:' + pct + '%;"></div></div>';
                }

                html += '<div class="okp-card-row"><span class="okp-total">' + money(card.total) + '</span>'
                    + '<span style="font-size:11px; color:#71717a;">' + timeAgo(card.created_at) + '</span></div>';

                el.innerHTML = html;
                el.addEventListener('click', function () { window.location.href = card.url; });
                return el;
            }

            function render(data) {
                let newApproval = false;
                let newMessage = false;

                Object.keys(columnMeta).forEach(function (key) {
                    const columnEl = root.querySelector('[data-column="' + key + '"]');
                    const body = columnEl.querySelector('[data-body]');
                    const countEl = columnEl.querySelector('[data-count]');
                    const footerEl = columnEl.querySelector('[data-footer]');
                    const cards = data.columns[key] || [];

                    countEl.textContent = cards.length;
                    body.innerHTML = '';

                    if (cards.length === 0) {
                        const empty = document.createElement('div');
                        empty.className = 'okp-empty';
                        empty.textContent = 'Nada aqui agora';
                        body.appendChild(empty);
                    }

                    cards.forEach(function (card) {
                        const el = card.type === 'order'
                            ? orderCard(card, columnMeta[key].color)
                            : sessionCard(card, columnMeta[key].color, key);
                        body.appendChild(el);

                        const alertId = card.type + '-' + card.id;
                        if (card.needs_approval && !seenApprovalIds.has(alertId)) {
                            seenApprovalIds.add(alertId);
                            if (!firstLoad) newApproval = true;
                        }
                        if (card.unread_messages > 0 && !seenMessageIds.has(alertId)) {
                            seenMessageIds.add(alertId);
                            if (!firstLoad) newMessage = true;
                        } else if (card.unread_messages === 0) {
                            seenMessageIds.delete(alertId);
                        }
                    });

                    if (footerEl && data.stage_times && data.stage_times[key] !== undefined) {
                        footerEl.textContent = data.stage_times[key] !== null
                            ? 'Média hoje: ' + data.stage_times[key] + ' min'
                            : '';
                    }
                });

                if (newApproval) beep(880);
                if (newMessage) beep(520);
                firstLoad = false;
            }

            async function poll() {
                try {
                    const res = await fetch(dataUrl, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    render(await res.json());
                } catch (e) { /* próxima rodada tenta de novo */ }
            }

            poll();
            pollTimer = setInterval(poll, 10000);
            window.addEventListener('beforeunload', function () { clearInterval(pollTimer); });

            // Tutorial obrigatório no primeiro acesso: spotlight + balão sobre
            // cada coluna, sem nenhum outro overlay por cima do kanban.
            function runTutorial() {
                if (localStorage.getItem(tutorialKey) === '1') return;

                const steps = [
                    { column: 'pendente', text: 'Aqui ficam os pedidos novos e as mesas com itens recém-chegados. Cards piscando em vermelho precisam da sua aprovação.' },
                    { column: 'em_preparo', text: 'Pedidos aprovados e mesas com itens já sendo preparados.' },
                    { column: 'pronto', text: 'Pedidos prontos ou em rota de entrega, e mesas com todos os itens servidos aguardando fechar a conta.' },
                    { column: 'entregue', text: 'Pedidos entregues e mesas já fechadas.' },
                ];

                let step = 0;
                const spotlight = document.createElement('div');
                spotlight.className = 'okp-tutorial-spotlight';
                const tip = document.createElement('div');
                tip.className = 'okp-tutorial-tip';
                root.appendChild(spotlight);
                root.appendChild(tip);

                function place() {
                    const columnEl = root.querySelector('[data-column="' + steps[step].column + '"]');
                    const rect = columnEl.getBoundingClientRect();
                    spotlight.style.top = (rect.top - 10) + 'px';
                    spotlight.style.left = (rect.left - 10) + 'px';
                    spotlight.style.width = (rect.width + 20) + 'px';
                    spotlight.style.height = (rect.height + 20) + 'px';

                    const tipLeft = Math.min(rect.right + 20, window.innerWidth - 320);
                    tip.style.top = (rect.top + 40) + 'px';
                    tip.style.left = tipLeft + 'px';
                    tip.innerHTML = '<div style="display:flex; align-items:center; gap:8px;">'
                        + '<span class="okp-tutorial-step-badge">' + (step + 1) + '</span>'
                        + '<span style="font-size:13px; font-weight:700; color:#fff;">Coluna ' + columnEl.querySelector('.okp-column-title').textContent.trim() + '</span></div>'
                        + '<p style="margin:0; font-size:13px; color:#d4d4d8; line-height:1.5;">' + steps[step].text + '</p>'
                        + '<div style="display:flex; justify-content:space-between; align-items:center;">'
                        + '<span style="font-size:12px; color:#71717a;">' + (step + 1) + ' de ' + steps.length + '</span>'
                        + '<div style="display:flex; gap:8px;">'
                        + '<button type="button" class="okp-tutorial-skip" data-skip>Pular</button>'
                        + '<button type="button" class="okp-tutorial-next" data-next>' + (step === steps.length - 1 ? 'Concluir' : 'Próximo →') + '</button>'
                        + '</div></div>';

                    tip.querySelector('[data-skip]').addEventListener('click', finish);
                    tip.querySelector('[data-next]').addEventListener('click', function () {
                        step += 1;
                        if (step >= steps.length) { finish(); return; }
                        place();
                    });
                }

                function finish() {
                    localStorage.setItem(tutorialKey, '1');
                    spotlight.remove();
                    tip.remove();
                }

                place();
            }

            setTimeout(runTutorial, 300);
        })();
    </script>
</x-filament-panels::page>
