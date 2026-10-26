@php
    $data = $this->getReportData();
    $labels = \App\Models\Order::paymentOptions();
    $methodColors = [
        'cash' => '#22c55e',
        'pix' => '#06b6d4',
        'debit' => '#6366f1',
        'credit' => '#e11d48',
    ];
    $grandTotal = $data['grandTotal'];
    $channelLabels = [
        \App\Models\Order::CHANNEL_ONLINE => 'Marketplace (online)',
        \App\Models\Order::CHANNEL_PRESENTIAL => 'Mesas (presencial)',
    ];
@endphp

<x-filament-panels::page>
    {{-- Classes utilitárias do Tailwind usadas aqui não existem no CSS compilado
         do painel admin (que só publica as classes que o próprio Filament usa
         internamente) — por isso este componente define suas próprias regras,
         escopadas sob .svr-root, em vez de depender de classes como "grid" ou
         "rounded-lg". --}}
    <style>
        .svr-root .svr-filterbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .svr-root .svr-filterbar select {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
        }
        html.dark .svr-root .svr-filterbar select {
            background: #1f2937;
            border-color: #374151;
            color: #f3f4f6;
        }
        .svr-root .svr-note {
            display: flex;
            gap: 0.625rem;
            align-items: flex-start;
            background: #fef3c7;
            border: 1px solid rgba(217, 119, 6, 0.35);
            border-radius: 0.75rem;
            padding: 0.75rem 0.875rem;
            margin-bottom: 1.25rem;
            font-size: 0.8125rem;
            line-height: 1.5;
            color: #92400e;
        }
        html.dark .svr-root .svr-note {
            background: rgba(217, 119, 6, 0.12);
            color: #fbbf24;
        }
        .svr-root .svr-tiles {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        @media (max-width: 920px) {
            .svr-root .svr-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 460px) {
            .svr-root .svr-tiles { grid-template-columns: 1fr; }
        }
        .svr-root .svr-tile {
            border-radius: 0.875rem;
            padding: 1rem 1rem 0.875rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            min-width: 0;
        }
        html.dark .svr-root .svr-tile {
            background: #1f2937;
            border-color: #374151;
        }
        .svr-root .svr-tile--total {
            background: linear-gradient(155deg, #e11d48, #be123c);
            border-color: transparent;
            color: #fff;
        }
        .svr-root .svr-tile-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            margin-bottom: 0.5rem;
        }
        html.dark .svr-root .svr-tile-label { color: #9ca3af; }
        .svr-root .svr-tile--total .svr-tile-label { color: rgba(255,255,255,0.85); }
        .svr-root .svr-dot {
            width: 0.5625rem;
            height: 0.5625rem;
            border-radius: 999px;
            flex: none;
        }
        .svr-root .svr-tile-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .svr-root .svr-tile-sub {
            margin-top: 0.375rem;
            font-size: 0.75rem;
            color: #9ca3af;
        }
        .svr-root .svr-tile--total .svr-tile-sub { color: rgba(255,255,255,0.75); }
        .svr-root .svr-tile-extra {
            margin-top: 0.375rem;
            font-size: 0.6875rem;
            color: #9ca3af;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .svr-root .svr-tile--total .svr-tile-extra { color: rgba(255,255,255,0.75); }
        .svr-root .svr-variation {
            display: inline-flex;
            align-items: center;
            gap: 0.1875rem;
            font-weight: 700;
        }
        .svr-root .svr-variation--up { color: #16a34a; }
        .svr-root .svr-variation--down { color: #dc2626; }
        html.dark .svr-root .svr-variation--up { color: #4ade80; }
        html.dark .svr-root .svr-variation--down { color: #f87171; }
        .svr-root .svr-tile--total .svr-variation--up { color: #bbf7d0; }
        .svr-root .svr-tile--total .svr-variation--down { color: #fecaca; }
        .svr-root .svr-grid-2 {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .svr-root .svr-grid-2 { grid-template-columns: 1fr; }
        }
        .svr-root .svr-bars {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }
        .svr-root .svr-bar-row {
            display: grid;
            grid-template-columns: 6.5rem 1fr 3.5rem;
            align-items: center;
            gap: 0.625rem;
        }
        .svr-root .svr-bar-row .svr-bar-label {
            font-size: 0.8125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4375rem;
        }
        .svr-root .svr-bar-track {
            height: 0.625rem;
            border-radius: 999px;
            background: #f3f4f6;
            overflow: hidden;
        }
        html.dark .svr-root .svr-bar-track { background: #374151; }
        .svr-root .svr-bar-track > i {
            display: block;
            height: 100%;
            border-radius: 999px;
        }
        .svr-root .svr-bar-row .svr-bar-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            text-align: right;
            color: #6b7280;
        }
        html.dark .svr-root .svr-bar-row .svr-bar-value { color: #9ca3af; }
        .svr-root .svr-table-wrap { overflow-x: auto; }
        .svr-root table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }
        .svr-root thead th {
            text-align: right;
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            font-weight: 700;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        html.dark .svr-root thead th { color: #9ca3af; border-color: #374151; }
        .svr-root thead th:first-child { text-align: left; }
        .svr-root tbody td {
            padding: 0.5rem 0.75rem;
            text-align: right;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            border-bottom: 1px solid #f3f4f6;
            color: #111827;
            white-space: nowrap;
        }
        html.dark .svr-root tbody td { color: #f3f4f6; border-color: #374151; }
        .svr-root tbody td:first-child {
            text-align: left;
            font-family: inherit;
            color: #6b7280;
            font-weight: 600;
        }
        html.dark .svr-root tbody td:first-child { color: #9ca3af; }
        .svr-root tbody tr.svr-total-row td {
            font-weight: 800;
            background: #f9fafb;
            color: #111827;
        }
        html.dark .svr-root tbody tr.svr-total-row td { background: #111827; color: #f3f4f6; }
        .svr-root .svr-empty {
            padding: 2rem 1rem;
            text-align: center;
            color: #9ca3af;
            font-size: 0.875rem;
        }
    </style>

    <div class="svr-root">
        <div class="svr-filterbar">
            <select wire:model.live="periodType">
                <option value="month">Mês</option>
                <option value="year">Ano completo</option>
            </select>

            @if ($periodType === 'month')
                <select wire:model.live="month">
                    @foreach ($this->getMonthOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="year">
                @foreach ($this->getYearOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="svr-note">
            <x-heroicon-o-information-circle style="width: 1.125rem; height: 1.125rem; flex-shrink: 0; margin-top: 1px;" />
            <span>
                Fiado não entra neste relatório, pois ainda não foi recebido. Pedidos feitos antes da
                separação entre débito e crédito no checkout do cliente final continuam contados como
                crédito — a distinção vale a partir de agora.
            </span>
        </div>

        <div class="svr-tiles">
            @foreach (\App\Filament\Admin\Pages\SalesReportByPaymentMethod::METHODS as $method)
                @php $variation = $data['variationByMethod'][$method]; @endphp
                <div class="svr-tile">
                    <div class="svr-tile-label">
                        <span class="svr-dot" style="background: {{ $methodColors[$method] }}"></span>
                        {{ $labels[$method] }}
                    </div>
                    <div class="svr-tile-value">R$ {{ number_format($data['totals'][$method], 2, ',', '.') }}</div>
                    <div class="svr-tile-sub">
                        {{ $data['counts'][$method] }} {{ $data['counts'][$method] === 1 ? 'venda' : 'vendas' }}
                        @if ($variation !== null)
                            · <span class="svr-variation {{ $variation >= 0 ? 'svr-variation--up' : 'svr-variation--down' }}">{{ $variation >= 0 ? '▲' : '▼' }} {{ number_format(abs($variation), 1, ',', '.') }}%</span>
                        @endif
                    </div>
                    <div class="svr-tile-extra">Ticket médio: R$ {{ number_format($data['averageTicket'][$method], 2, ',', '.') }}</div>
                </div>
            @endforeach

            <div class="svr-tile svr-tile--total">
                <div class="svr-tile-label">Total do período</div>
                <div class="svr-tile-value">R$ {{ number_format($grandTotal, 2, ',', '.') }}</div>
                <div class="svr-tile-sub">
                    {{ $data['grandCount'] }} {{ $data['grandCount'] === 1 ? 'venda finalizada' : 'vendas finalizadas' }}
                    @if ($data['grandTotalVariation'] !== null)
                        · <span class="svr-variation {{ $data['grandTotalVariation'] >= 0 ? 'svr-variation--up' : 'svr-variation--down' }}">{{ $data['grandTotalVariation'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($data['grandTotalVariation']), 1, ',', '.') }}%</span>
                    @endif
                </div>
                <div class="svr-tile-extra">
                    @if ($data['previousGrandTotal'] > 0)
                        Período anterior: R$ {{ number_format($data['previousGrandTotal'], 2, ',', '.') }}
                    @else
                        Sem venda no período anterior pra comparar
                    @endif
                </div>
            </div>
        </div>

        <x-filament::section heading="Fechamento {{ $periodType === 'year' ? 'mensal' : 'diário' }}">
            @if ($data['breakdown']->isEmpty())
                <div class="svr-empty">Nenhuma venda finalizada neste período.</div>
            @else
                <div class="svr-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ $periodType === 'year' ? 'Mês' : 'Dia' }}</th>
                                @foreach (\App\Filament\Admin\Pages\SalesReportByPaymentMethod::METHODS as $method)
                                    <th>{{ $labels[$method] }}</th>
                                @endforeach
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['breakdown'] as $periodKey => $row)
                                <tr>
                                    <td>
                                        {{ $periodType === 'year'
                                            ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $periodKey.'-01')->translatedFormat('M/Y')
                                            : \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $periodKey)->translatedFormat('D, d') }}
                                    </td>
                                    @foreach (\App\Filament\Admin\Pages\SalesReportByPaymentMethod::METHODS as $method)
                                        <td>{{ $row[$method] > 0 ? number_format($row[$method], 2, ',', '.') : '—' }}</td>
                                    @endforeach
                                    <td>{{ number_format(array_sum($row), 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="svr-total-row">
                                <td>Total</td>
                                @foreach (\App\Filament\Admin\Pages\SalesReportByPaymentMethod::METHODS as $method)
                                    <td>{{ number_format($data['totals'][$method], 2, ',', '.') }}</td>
                                @endforeach
                                <td>{{ number_format($grandTotal, 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <div class="svr-grid-2">
            <x-filament::section heading="Participação por forma de pagamento">
                @if ($grandTotal <= 0)
                    <div class="svr-empty">Nenhuma venda finalizada neste período.</div>
                @else
                    <div class="svr-bars">
                        @foreach (collect(\App\Filament\Admin\Pages\SalesReportByPaymentMethod::METHODS)->sortByDesc(fn ($method) => $data['totals'][$method]) as $method)
                            @php $share = $grandTotal > 0 ? ($data['totals'][$method] / $grandTotal) * 100 : 0; @endphp
                            <div class="svr-bar-row">
                                <div class="svr-bar-label">
                                    <span class="svr-dot" style="background: {{ $methodColors[$method] }}"></span>
                                    {{ $labels[$method] }}
                                </div>
                                <div class="svr-bar-track"><i style="width: {{ number_format($share, 2, '.', '') }}%; background: {{ $methodColors[$method] }}"></i></div>
                                <div class="svr-bar-value">{{ number_format($share, 1, ',', '.') }}%</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Por canal">
                @if ($grandTotal <= 0)
                    <div class="svr-empty">Nenhuma venda finalizada neste período.</div>
                @else
                    <div class="svr-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="text-align: left;">Canal</th>
                                    <th>Total</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($channelLabels as $channel => $channelLabel)
                                    @php
                                        $channelTotal = array_sum($data['channelTotals'][$channel]);
                                        $channelShare = $grandTotal > 0 ? ($channelTotal / $grandTotal) * 100 : 0;
                                    @endphp
                                    <tr>
                                        <td style="text-align: left; font-family: inherit; font-weight: 600; color: inherit;">{{ $channelLabel }}</td>
                                        <td>R$ {{ number_format($channelTotal, 2, ',', '.') }}</td>
                                        <td>{{ number_format($channelShare, 1, ',', '.') }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
