<?php

namespace App\Console\Commands;

use App\Models\FavoredTransaction;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class RemindFavoredDueDates extends Command
{
    protected $signature = 'favored:remind-due-dates';

    protected $description = 'Avisa cada empresa, por push, sobre fiados não pagos que vencem no dia seguinte';

    public function handle(PushNotificationService $pushNotificationService): int
    {
        $transactions = FavoredTransaction::with('company')
            ->where('active', true)
            ->whereDate('due_date', now()->addDay()->toDateString())
            ->whereColumn('favored_paid_amount', '<', 'favored_total')
            ->get()
            ->groupBy('company_id');

        foreach ($transactions as $companyId => $companyTransactions) {
            $count = $companyTransactions->count();
            $total = $companyTransactions->sum(fn (FavoredTransaction $t) => $t->favored_total - $t->favored_paid_amount);
            $companyUuid = $companyTransactions->first()->company?->uuid;

            $pushNotificationService->notifyCompany(
                $companyId,
                '⏰ Fiado vencendo amanhã',
                $count.' '.($count === 1 ? 'fiado vence' : 'fiados vencem').' amanhã, totalizando R$ '.number_format($total, 2, ',', '.').'.',
                $companyUuid ? "/admin/{$companyUuid}/favored-transactions" : '/admin'
            );
        }

        $this->info('Lembretes de vencimento enviados para '.$transactions->count().' empresa(s).');

        return self::SUCCESS;
    }
}
