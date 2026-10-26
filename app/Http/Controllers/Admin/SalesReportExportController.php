<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportExportController extends Controller
{
    public function export(Request $request, Company $company, SalesReportService $reportService): StreamedResponse
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $periodType = $request->query('period_type') === 'year' ? 'year' : 'month';
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $data = $reportService->report($company, $periodType, $month, $year);
        $labels = Order::paymentOptions();

        $filename = 'relatorio-vendas-'.($periodType === 'year'
            ? $year
            : sprintf('%04d-%02d', $year, $month)).'.csv';

        return response()->streamDownload(function () use ($data, $labels, $periodType) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_merge(
                [$periodType === 'year' ? 'Mês' : 'Dia'],
                array_map(fn (string $method): string => $labels[$method], SalesReportService::METHODS),
                ['Total'],
            ));

            foreach ($data['breakdown'] as $periodKey => $row) {
                $label = $periodType === 'year'
                    ? Carbon::createFromFormat('Y-m-d', $periodKey.'-01')->translatedFormat('m/Y')
                    : Carbon::createFromFormat('Y-m-d', $periodKey)->translatedFormat('d/m/Y');

                fputcsv($handle, array_merge(
                    [$label],
                    array_map(fn (string $method): string => number_format($row[$method], 2, '.', ''), SalesReportService::METHODS),
                    [number_format(array_sum($row), 2, '.', '')],
                ));
            }

            fputcsv($handle, array_merge(
                ['Total'],
                array_map(fn (string $method): string => number_format($data['totals'][$method], 2, '.', ''), SalesReportService::METHODS),
                [number_format($data['grandTotal'], 2, '.', '')],
            ));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
