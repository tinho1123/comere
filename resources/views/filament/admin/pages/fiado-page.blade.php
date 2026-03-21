<x-filament-panels::page>
    @if ($this->selectedClient)
        @php $summary = $this->getClientSummary(); @endphp

        <x-filament-widgets::stats-overview-widget>
            <x-filament-widgets::stats-overview-widget-stat
                :label="__('Total Fiado')"
                :value="'R$ ' . number_format($summary?->total_debt ?? 0, 2, ',', '.')"
                color="primary"
                icon="heroicon-o-credit-card"
            />
            <x-filament-widgets::stats-overview-widget-stat
                :label="__('Pago')"
                :value="'R$ ' . number_format($summary?->total_paid ?? 0, 2, ',', '.')"
                color="success"
                icon="heroicon-o-check-circle"
            />
            <x-filament-widgets::stats-overview-widget-stat
                :label="__('Saldo Devedor')"
                :value="'R$ ' . number_format($summary?->remaining_balance ?? 0, 2, ',', '.')"
                :color="($summary?->remaining_balance ?? 0) > 0 ? 'danger' : 'success'"
                icon="heroicon-o-banknotes"
            />
        </x-filament-widgets::stats-overview-widget>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
