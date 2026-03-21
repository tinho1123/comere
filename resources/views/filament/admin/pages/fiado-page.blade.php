<x-filament-panels::page>
    @if ($this->selectedClient)
        {{-- Resumo do cliente --}}
        @php
            $summary = \App\Models\FavoredTransaction::query()
                ->where('company_id', \Filament\Facades\Filament::getTenant()->id)
                ->where('client_name', $this->selectedClient)
                ->selectRaw('COUNT(*) as items_count, SUM(favored_total) as total_debt, SUM(favored_paid_amount) as total_paid, (SUM(favored_total) - SUM(favored_paid_amount)) as remaining_balance')
                ->first();
        @endphp

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total fiado</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">
                    R$ {{ number_format($summary->total_debt ?? 0, 2, ',', '.') }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Pago</p>
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                    R$ {{ number_format($summary->total_paid ?? 0, 2, ',', '.') }}
                </p>
            </div>
            <div class="rounded-xl border {{ ($summary->remaining_balance ?? 0) > 0 ? 'border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/30' : 'border-gray-200 dark:border-white/10 bg-white dark:bg-white/5' }} p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo devedor</p>
                <p class="text-xl font-bold {{ ($summary->remaining_balance ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">
                    R$ {{ number_format($summary->remaining_balance ?? 0, 2, ',', '.') }}
                </p>
            </div>
        </div>

        {{-- Tabela de produtos do cliente --}}
        {{ $this->table }}
    @else
        {{-- Lista de clientes com fiado --}}
        @php $clients = $this->getClientSummaries(); @endphp

        @if ($clients->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <x-filament::icon icon="heroicon-o-credit-card" class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                <p class="text-gray-500 dark:text-gray-400 font-medium">Nenhum fiado registrado ainda.</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Clique em "Novo Fiado" para registrar o primeiro.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($clients as $client)
                    <button
                        wire:click="selectClient('{{ addslashes($client->client_name) }}')"
                        class="text-left rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-md transition-all group"
                    >
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center flex-shrink-0">
                                    <span class="text-primary-700 dark:text-primary-400 font-bold text-sm">
                                        {{ mb_strtoupper(mb_substr($client->client_name, 0, 2)) }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                        {{ $client->client_name }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $client->items_count }} {{ $client->items_count == 1 ? 'produto' : 'produtos' }}
                                    </p>
                                </div>
                            </div>

                            @if ($client->remaining_balance > 0)
                                <span class="text-xs font-semibold bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 px-2 py-0.5 rounded-full">
                                    Devendo
                                </span>
                            @else
                                <span class="text-xs font-semibold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 px-2 py-0.5 rounded-full">
                                    Quitado
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm border-t border-gray-100 dark:border-white/5 pt-3 mt-1">
                            <div>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Total fiado</p>
                                <p class="font-medium text-gray-700 dark:text-gray-300">
                                    R$ {{ number_format($client->total_debt, 2, ',', '.') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Saldo devedor</p>
                                <p class="font-bold {{ $client->remaining_balance > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    R$ {{ number_format($client->remaining_balance, 2, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
