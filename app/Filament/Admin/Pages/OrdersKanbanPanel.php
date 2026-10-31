<?php

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;

class OrdersKanbanPanel extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Painel de Pedidos';

    protected static ?string $title = 'Painel de Pedidos';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.admin.pages.orders-kanban-panel';

    public static function getNavigationGroup(): ?string
    {
        return 'Vendas';
    }
}
