<x-filament-panels::page>
    @if ($this->selectedDriver())
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
            <x-filament::section>
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Motorista</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $this->selectedDriver()->name }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Veículo</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">
                    {{ $this->selectedDriver()->vehicle_type === \App\Models\Driver::VEHICLE_MOTOBOY ? 'Motoboy' : 'Carro' }}
                </p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Telefone</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $this->selectedDriver()->phone ?? '—' }}</p>
            </x-filament::section>
        </div>
    @endif

    {{ $this->table }}

    @unless ($this->selectedDriver())
        <x-filament::section heading="Histórico de repasses">
            @php $history = $this->getPayoutHistory(); @endphp

            @if ($history->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum repasse confirmado ainda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">Motorista</th>
                                <th class="py-2 pr-4">Entregas</th>
                                <th class="py-2 pr-4">Método</th>
                                <th class="py-2 pr-4 text-right">Valor</th>
                                <th class="py-2">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($history as $payout)
                                <tr>
                                    <td class="py-2.5 pr-4 font-semibold text-gray-950 dark:text-white">{{ $payout->driver->name }}</td>
                                    <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400">{{ $payout->deliveries_count }} entregas</td>
                                    <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400">{{ $payout->methodLabel() }}</td>
                                    <td class="py-2.5 pr-4 text-right font-bold text-gray-950 dark:text-white">R$ {{ number_format($payout->total_amount, 2, ',', '.') }}</td>
                                    <td class="py-2.5 text-gray-500 dark:text-gray-400">{{ $payout->paid_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endunless
</x-filament-panels::page>
