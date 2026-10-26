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
            <x-heroicon-o-exclamation-triangle style="width: 1.125rem; height: 1.125rem; flex-shrink: 0; margin-top: 1px;" />
            <span>
                <strong>Débito e crédito ainda não se separam no pedido online.</strong>
                No checkout do cliente final hoje só existe o botão "Cartão", que grava como crédito.
                Pedidos criados pelo admin e fechamentos de mesa já distinguem os dois — por isso o
                débito abaixo vem só dessas origens. Fiado não entra neste relatório, pois ainda não
                foi recebido.
            </span>
        </div>

        <div class="svr-tiles">
            @foreach (\App\Filament\Admin\Pages\SalesReportByPaymentMethod::METHODS as $method)
                <div class="svr-tile">
                    <div class="svr-tile-label">
                        <span class="svr-dot" style="background: {{ $methodColors[$method] }}"></span>
                        {{ $labels[$method] }}
                    </div>
                    <div class="svr-tile-value">R$ {{ number_format($data['totals'][$method], 2, ',', '.') }}</div>
                    <div class="svr-tile-sub">{{ $data['counts'][$method] }} {{ $data['counts'][$method] === 1 ? 'venda' : 'vendas' }}</div>
                </div>
            @endforeach

            <div class="svr-tile svr-tile--total">
                <div class="svr-tile-label">Total do período</div>
                <div class="svr-tile-value">R$ {{ number_format($grandTotal, 2, ',', '.') }}</div>
                <div class="svr-tile-sub">{{ $data['grandCount'] }} {{ $data['grandCount'] === 1 ? 'venda finalizada' : 'vendas finalizadas' }}</div>
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
                                            ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $periodKey)->translatedFormat('M/Y')
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
    </div>
</x-filament-panels::page>
