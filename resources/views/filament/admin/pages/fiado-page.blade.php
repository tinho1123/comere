<x-filament-panels::page>
    @if ($this->selectedClient)
        @php($summary = $this->getClientSummary())
        <style>
            /* Mesma causa raiz do bug corrigido em order-location.blade.php:
               classes Tailwind usadas só aqui não existem no CSS compilado
               do painel admin, então este bloco define seu próprio CSS
               escopado em vez de depender de "grid", "bg-gray-50" etc. */
            .fp-stats {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
                margin-bottom: 1.5rem;
            }
            @media (min-width: 640px) {
                .fp-stats {
                    grid-template-columns: repeat(3, 1fr);
                }
            }
            .fp-stat {
                border-radius: 0.75rem;
                padding: 1.25rem;
                background: #ffffff;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }
            html.dark .fp-stat {
                background: #1e293b;
            }
            .fp-stat-label {
                font-size: 0.875rem;
                font-weight: 500;
                color: #6b7280;
                margin: 0 0 0.5rem;
            }
            html.dark .fp-stat-label {
                color: #9ca3af;
            }
            .fp-stat-value {
                font-size: 1.5rem;
                font-weight: 700;
                margin: 0;
            }
            .fp-stat-value--success { color: #059669; }
            .fp-stat-value--danger { color: #dc2626; }
            html.dark .fp-stat-value--success { color: #34d399; }
            html.dark .fp-stat-value--danger { color: #f87171; }
        </style>

        <div class="fp-stats">
            <div class="fp-stat">
                <p class="fp-stat-label">Total Fiado</p>
                <p class="fp-stat-value">R$ {{ number_format($summary?->total_debt ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="fp-stat">
                <p class="fp-stat-label">Pago</p>
                <p class="fp-stat-value fp-stat-value--success">R$ {{ number_format($summary?->total_paid ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="fp-stat">
                <p class="fp-stat-label">Saldo Devedor</p>
                <p class="fp-stat-value {{ ($summary?->remaining_balance ?? 0) > 0 ? 'fp-stat-value--danger' : 'fp-stat-value--success' }}">
                    R$ {{ number_format($summary?->remaining_balance ?? 0, 2, ',', '.') }}
                </p>
            </div>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
