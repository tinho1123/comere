<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BillingService
{
    /**
     * Gera o ciclo de cobrança do mês atual para uma empresa.
     * Não cria duplicatas (unique: company_id + period_start).
     */
    public function generateCycleForCompany(Company $company, ?Carbon $referenceDate = null): ?BillingCycle
    {
        $setting = $company->billingSetting;

        if (! $setting || $setting->fee_per_transaction <= 0) {
            return null;
        }

        $ref = ($referenceDate ?? now())->copy()->startOfMonth();
        $periodStart = $ref->copy()->startOfMonth()->toDateString();
        $periodEnd = $ref->copy()->endOfMonth()->toDateString();

        // Evita duplicata
        if (BillingCycle::where('company_id', $company->id)->where('period_start', $periodStart)->exists()) {
            return null;
        }

        $paymentDay = min($setting->payment_day, 28);
        $dueDate = $ref->copy()->addMonthNoOverflow()->setDay($paymentDay)->toDateString();

        $transactionCount = $company->orders()
            ->whereBetween('created_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $totalAmount = $transactionCount * (float) $setting->fee_per_transaction;

        return BillingCycle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'transaction_count' => $transactionCount,
            'fee_per_transaction' => $setting->fee_per_transaction,
            'total_amount' => $totalAmount,
            'status' => 'pending',
        ]);
    }

    /**
     * Gera ciclos para todas as empresas com billing_settings configurado.
     */
    public function generateCurrentMonthForAll(): void
    {
        Company::whereHas('billingSetting', fn ($q) => $q->where('fee_per_transaction', '>', 0))
            ->each(fn (Company $company) => $this->generateCycleForCompany($company));
    }

    /**
     * Recalcula um ciclo existente (ex: após confirmar novos pedidos no período).
     */
    public function recalculateCycle(BillingCycle $cycle): void
    {
        if ($cycle->isPaid()) {
            return;
        }

        $count = $cycle->company->orders()
            ->whereBetween('created_at', [
                $cycle->period_start->startOfDay(),
                $cycle->period_end->endOfDay(),
            ])
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $cycle->update([
            'transaction_count' => $count,
            'total_amount' => $count * (float) $cycle->fee_per_transaction,
        ]);
    }

    /**
     * Métricas dos últimos N meses para uma empresa.
     */
    public function getMonthlyMetrics(Company $company, int $months = 6): array
    {
        $cycles = $company->billingCycles()
            ->orderByDesc('period_start')
            ->limit($months)
            ->get();

        return $cycles->map(function (BillingCycle $cycle, int $index) use ($cycles) {
            $prev = $cycles->get($index + 1);
            $change = null;

            if ($prev && $prev->total_amount > 0) {
                $change = round(
                    (((float) $cycle->total_amount - (float) $prev->total_amount) / (float) $prev->total_amount) * 100,
                    1
                );
            }

            return [
                'period' => $cycle->period_start->format('m/Y'),
                'transaction_count' => $cycle->transaction_count,
                'total_amount' => (float) $cycle->total_amount,
                'status' => $cycle->status,
                'change_percent' => $change,
            ];
        })->toArray();
    }

    /**
     * Marca ciclos pendentes como 'overdue' se a due_date já passou.
     */
    public function markOverdue(): void
    {
        BillingCycle::where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }
}
