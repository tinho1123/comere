<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesReportService
{
    public const METHODS = [
        Order::PAYMENT_CASH,
        Order::PAYMENT_PIX,
        Order::PAYMENT_DEBIT,
        Order::PAYMENT_CREDIT,
    ];

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function periodBounds(string $periodType, int $month, int $year): array
    {
        if ($periodType === 'year') {
            $start = Carbon::create($year, 1, 1)->startOfYear();
            $end = Carbon::create($year, 12, 31)->endOfYear();
        } else {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{month: int, year: int}
     */
    public function previousPeriod(string $periodType, int $month, int $year): array
    {
        if ($periodType === 'year') {
            return ['month' => $month, 'year' => $year - 1];
        }

        $previous = Carbon::create($year, $month, 1)->subMonth();

        return ['month' => (int) $previous->format('n'), 'year' => (int) $previous->format('Y')];
    }

    /**
     * Totals, counts, average ticket, channel split and a daily/monthly
     * breakdown of delivered sales by payment method, for a single period.
     * Fiado is intentionally excluded — it hasn't been collected yet.
     *
     * @return array{
     *     periodType: string,
     *     totals: array<string, float>,
     *     counts: array<string, int>,
     *     grandTotal: float,
     *     grandCount: int,
     *     averageTicket: array<string, float>,
     *     channelTotals: array<string, array<string, float>>,
     *     breakdown: Collection<string, array<string, float>>,
     * }
     */
    public function report(Company $company, string $periodType, int $month, int $year): array
    {
        $period = $this->periodBounds($periodType, $month, $year);

        $orders = Order::query()
            ->where('company_id', $company->id)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereIn('payment_method', self::METHODS)
            ->whereBetween('delivered_at', [$period['start'], $period['end']])
            ->get(['payment_method', 'total_amount', 'delivered_at', 'channel']);

        $totals = array_fill_keys(self::METHODS, 0.0);
        $counts = array_fill_keys(self::METHODS, 0);
        $channelTotals = [
            Order::CHANNEL_ONLINE => array_fill_keys(self::METHODS, 0.0),
            Order::CHANNEL_PRESENTIAL => array_fill_keys(self::METHODS, 0.0),
        ];
        $breakdown = [];
        $groupFormat = $periodType === 'year' ? 'Y-m' : 'Y-m-d';

        foreach ($orders as $order) {
            $method = $order->payment_method;
            $amount = (float) $order->total_amount;

            $totals[$method] += $amount;
            $counts[$method]++;

            $channel = $order->channel === Order::CHANNEL_ONLINE ? Order::CHANNEL_ONLINE : Order::CHANNEL_PRESENTIAL;
            $channelTotals[$channel][$method] += $amount;

            $periodKey = $order->delivered_at->format($groupFormat);
            $breakdown[$periodKey] ??= array_fill_keys(self::METHODS, 0.0);
            $breakdown[$periodKey][$method] += $amount;
        }

        ksort($breakdown);

        $averageTicket = [];
        foreach (self::METHODS as $method) {
            $averageTicket[$method] = $counts[$method] > 0 ? $totals[$method] / $counts[$method] : 0.0;
        }

        return [
            'periodType' => $periodType,
            'totals' => $totals,
            'counts' => $counts,
            'grandTotal' => array_sum($totals),
            'grandCount' => array_sum($counts),
            'averageTicket' => $averageTicket,
            'channelTotals' => $channelTotals,
            'breakdown' => collect($breakdown),
        ];
    }

    /**
     * Same as report(), plus the previous period's totals and the
     * percentage variation (null when there is no previous total to
     * compare against).
     */
    public function reportWithComparison(Company $company, string $periodType, int $month, int $year): array
    {
        $current = $this->report($company, $periodType, $month, $year);

        $previousParams = $this->previousPeriod($periodType, $month, $year);
        $previous = $this->report($company, $periodType, $previousParams['month'], $previousParams['year']);

        $current['previousGrandTotal'] = $previous['grandTotal'];
        $current['grandTotalVariation'] = $this->percentVariation($current['grandTotal'], $previous['grandTotal']);

        $current['previousTotals'] = $previous['totals'];
        $current['variationByMethod'] = [];
        foreach (self::METHODS as $method) {
            $current['variationByMethod'][$method] = $this->percentVariation($current['totals'][$method], $previous['totals'][$method]);
        }

        return $current;
    }

    /**
     * Null when there is no previous total to compare against (avoids a
     * division by zero and a meaningless "+infinite%").
     */
    public function percentVariation(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
