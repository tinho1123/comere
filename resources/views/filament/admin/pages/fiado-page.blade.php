<x-filament-panels::page>
    @if ($this->selectedClient)
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
            @foreach ($this->getClientStats() as $stat)
                {!! $stat->toHtml() !!}
            @endforeach
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
