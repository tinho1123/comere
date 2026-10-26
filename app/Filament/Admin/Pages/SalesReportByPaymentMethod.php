<?php

namespace App\Filament\Admin\Pages;

use App\Services\SalesReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SalesReportByPaymentMethod extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Relatório de Vendas';

    protected static ?string $title = 'Relatório de Vendas por Forma de Pagamento';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.admin.pages.sales-report-by-payment-method';

    public const METHODS = SalesReportService::METHODS;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('admin.sales-report.export', [
                    'company' => Filament::getTenant(),
                    'period_type' => $this->periodType,
                    'month' => $this->month,
                    'year' => $this->year,
                ]))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @return array{
     *     periodType: string,
     *     totals: array<string, float>,
     *     counts: array<string, int>,
     *     grandTotal: float,
     *     grandCount: int,
     *     averageTicket: array<string, float>,
     *     channelTotals: array<string, array<string, float>>,
     *     breakdown: Collection<string, array<string, float>>,
     *     previousGrandTotal: float,
     *     grandTotalVariation: ?float,
     *     previousTotals: array<string, float>,
     *     variationByMethod: array<string, ?float>,
     * }
     */
    public function getReportData(): array
    {
        return app(SalesReportService::class)->reportWithComparison(
            Filament::getTenant(),
            $this->periodType,
            $this->month,
            $this->year,
        );
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
