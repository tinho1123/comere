<?php

namespace App\Filament\Admin\Pages;

use App\Models\Order;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesReportByPaymentMethod extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Relatório de Vendas';

    protected static ?string $title = 'Relatório de Vendas por Forma de Pagamento';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.admin.pages.sales-report-by-payment-method';

    public const METHODS = [
        Order::PAYMENT_CASH,
        Order::PAYMENT_PIX,
        Order::PAYMENT_DEBIT,
        Order::PAYMENT_CREDIT,
    ];

    public string $periodType = 'month';

    public int $month;

    public int $year;

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public function mount(): void
    {
        $this->month = (int) now()->format('n');
        $this->year = (int) now()->format('Y');
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getPeriod(): array
    {
        if ($this->periodType === 'year') {
            $start = Carbon::create($this->year, 1, 1)->startOfYear();
            $end = Carbon::create($this->year, 12, 31)->endOfYear();
        } else {
            $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{
     *     totals: array<string, float>,
     *     counts: array<string, int>,
     *     grandTotal: float,
     *     grandCount: int,
     *     breakdown: Collection<string, array<string, float>>,
     * }
     */
    public function getReportData(): array
    {
        $company = Filament::getTenant();
        $period = $this->getPeriod();

        $orders = Order::query()
            ->where('company_id', $company->id)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereIn('payment_method', self::METHODS)
            ->whereBetween('delivered_at', [$period['start'], $period['end']])
            ->get(['payment_method', 'total_amount', 'delivered_at']);

        $totals = array_fill_keys(self::METHODS, 0.0);
        $counts = array_fill_keys(self::METHODS, 0);
        $breakdown = [];

        $groupFormat = $this->periodType === 'year' ? 'Y-m' : 'Y-m-d';

        foreach ($orders as $order) {
            $method = $order->payment_method;
            $amount = (float) $order->total_amount;

            $totals[$method] += $amount;
            $counts[$method]++;

            $periodKey = $order->delivered_at->format($groupFormat);
            $breakdown[$periodKey] ??= array_fill_keys(self::METHODS, 0.0);
            $breakdown[$periodKey][$method] += $amount;
        }

        ksort($breakdown);

        return [
            'totals' => $totals,
            'counts' => $counts,
            'grandTotal' => array_sum($totals),
            'grandCount' => array_sum($counts),
            'breakdown' => collect($breakdown),
        ];
    }

    public function getMonthOptions(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    public function getYearOptions(): array
    {
        $currentYear = (int) now()->format('Y');

        return array_combine(
            range($currentYear, $currentYear - 4),
            range($currentYear, $currentYear - 4),
        );
    }
}
