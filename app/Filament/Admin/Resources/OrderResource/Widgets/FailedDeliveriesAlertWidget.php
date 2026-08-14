<?php

namespace App\Filament\Admin\Resources\OrderResource\Widgets;

use App\Models\Delivery;
use App\Models\Order;
use Filament\Widgets\Widget;

class FailedDeliveriesAlertWidget extends Widget
{
    protected string $view = 'filament.admin.resources.order-resource.widgets.failed-deliveries-alert';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $company = filament()->getTenant();

        $count = Order::where('company_id', $company->id)
            ->where('status', Order::STATUS_SHIPPED)
            ->whereHas('delivery', fn ($q) => $q->where('status', Delivery::STATUS_FAILED))
            ->count();

        return ['count' => $count];
    }
}
