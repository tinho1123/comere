<?php

namespace App\Filament\Admin\Resources\DeliveryResource\Pages;

use App\Filament\Admin\Resources\DeliveryResource;
use App\Models\Delivery;
use Filament\Facades\Filament;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    public function getTabs(): array
    {
        $companyId = Filament::getTenant()->id;

        return [
            'all' => Tab::make('Todas')
                ->icon('heroicon-m-list-bullet'),

            'dispatched' => Tab::make('Em rota')
                ->icon('heroicon-m-truck')
                ->badge(
                    Delivery::where('company_id', $companyId)
                        ->where('status', Delivery::STATUS_DISPATCHED)
                        ->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Delivery::STATUS_DISPATCHED)),

            'delivered' => Tab::make('Entregues')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Delivery::STATUS_DELIVERED)),

            'unpaid' => Tab::make('Pagamento pendente')
                ->icon('heroicon-m-banknotes')
                ->badge(
                    Delivery::where('company_id', $companyId)
                        ->where('status', Delivery::STATUS_DELIVERED)
                        ->where('is_paid', false)
                        ->count()
                )
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', Delivery::STATUS_DELIVERED)
                    ->where('is_paid', false)
                ),
        ];
    }
}
