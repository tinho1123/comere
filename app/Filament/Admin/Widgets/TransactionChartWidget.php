<?php

namespace App\Filament\Admin\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TransactionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Transações por Mês (R$)';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $company = filament()->getTenant();

        $raw = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('company_id', $company->id))
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as total, COUNT(*) as qty')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $labels = [];
        $totals = [];
        $quantities = [];

        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->translatedFormat('M/Y');
            $totals[] = round((float) ($raw->get($key)?->total ?? 0), 2);
            $quantities[] = (int) ($raw->get($key)?->qty ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total (R$)',
                    'data' => $totals,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.6)',
                    'borderColor' => 'rgb(99, 102, 241)',
                    'borderWidth' => 1,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Qtd. itens',
                    'data' => $quantities,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.4)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                    'type' => 'line',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['position' => 'left', 'title' => ['display' => true, 'text' => 'R$']],
                'y1' => ['position' => 'right', 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'Qtd']],
            ],
        ];
    }
}
