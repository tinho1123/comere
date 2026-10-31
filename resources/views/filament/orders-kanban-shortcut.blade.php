@php
    try {
        $tenant = \Filament\Facades\Filament::getTenant();
        $kanbanUrl = $tenant ? \App\Filament\Admin\Pages\OrdersKanbanPanel::getUrl(tenant: $tenant) : null;
    } catch (\Throwable) {
        $kanbanUrl = null;
    }
@endphp

@if ($kanbanUrl)
    <script>
        (function () {
            const url = @js($kanbanUrl);
            document.addEventListener('keydown', function (e) {
                const target = e.target;
                const isTyping = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
                if (isTyping) return;

                // Shift+P abre o Painel de Pedidos em tela cheia de qualquer tela do admin.
                if (e.shiftKey && e.key.toLowerCase() === 'p') {
                    e.preventDefault();
                    window.location.href = url;
                }
            });
        })();
    </script>
@endif
