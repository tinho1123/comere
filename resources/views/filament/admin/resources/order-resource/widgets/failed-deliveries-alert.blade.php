@if ($count > 0)
    <div class="fi-wi-widget">
        <div class="flex items-center gap-3 rounded-xl border border-danger-300 bg-danger-50 px-4 py-3 dark:border-danger-700 dark:bg-danger-500/10">
            <x-heroicon-o-exclamation-triangle class="h-6 w-6 shrink-0 text-danger-600 dark:text-danger-400" />
            <p class="text-sm font-medium text-danger-700 dark:text-danger-300">
                {{ $count }} {{ $count === 1 ? 'entrega com problema precisa' : 'entregas com problema precisam' }} de atenção — reatribua a outro motorista ou cancele o pedido.
            </p>
        </div>
    </div>
@endif
