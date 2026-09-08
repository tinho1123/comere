<?php

namespace App\Filament\Admin\Resources\ProductResource\Pages;

use App\Filament\Admin\Resources\ProductResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Produtos';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_menu')
                ->label('Exportar Cardápio')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('admin.products.menu', Filament::getTenant()->uuid))
                ->openUrlInNewTab(),
            Actions\CreateAction::make(),
        ];
    }
}
