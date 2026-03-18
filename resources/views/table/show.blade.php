<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $table->name }} — {{ $table->company->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f8fafc; font-family: system-ui, sans-serif; }
        .tab-btn.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-start pb-12">

    {{-- Header fixo --}}
    <div class="w-full bg-white border-b border-gray-100 sticky top-0 z-10">
        <div class="max-w-md mx-auto px-4 py-3">
            <p class="text-xs text-gray-400">{{ $table->company->name }}</p>
            <h1 class="text-lg font-bold text-gray-900 leading-tight">{{ $table->name }}</h1>
        </div>

        @if ($table->activeSession)
            {{-- Abas --}}
            <div class="max-w-md mx-auto flex border-t border-gray-100">
                <button onclick="switchTab('cardapio')" id="tab-cardapio"
                    class="tab-btn active flex-1 py-3 text-sm text-gray-500 transition-colors">
                    Cardápio
                </button>
                <button onclick="switchTab('consumo')" id="tab-consumo"
                    class="tab-btn flex-1 py-3 text-sm text-gray-500 transition-colors">
                    Meu consumo
                    @if ($table->activeSession->items->isNotEmpty())
                        <span class="ml-1 inline-flex items-center justify-center w-5 h-5 text-xs bg-indigo-100 text-indigo-700 rounded-full">
                            {{ $table->activeSession->items->count() }}
                        </span>
                    @endif
                </button>
            </div>
        @endif
    </div>

    <div class="w-full max-w-md px-4 pt-4">

        {{-- Alertas --}}
        @if (session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if ($table->activeSession)
            @php $session = $table->activeSession; @endphp

            {{-- Registro de nome (quando não há cliente) --}}
            @if (! $session->client && ! $session->guest_name)
                <div class="mb-4 bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p class="text-sm text-amber-800 font-medium mb-2">Informe seu nome para fazer pedidos</p>
                    <form method="POST" action="{{ route('table.register-name', $table->uuid) }}" class="flex gap-2">
                        @csrf
                        <input
                            type="text"
                            name="guest_name"
                            placeholder="Seu nome"
                            required
                            class="flex-1 border border-amber-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"
                        >
                        <button type="submit" class="bg-amber-500 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-amber-600">
                            Registrar
                        </button>
                    </form>
                    @error('guest_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- ABA: CARDÁPIO --}}
            <div id="content-cardapio" class="tab-content active">

                @if ($products->isEmpty())
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                        <p class="text-sm text-gray-400">Nenhum produto disponível no momento.</p>
                    </div>
                @else
                    @foreach ($products as $category => $items)
                        <div class="mb-5">
                            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">
                                {{ $category }}
                            </h2>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-50 overflow-hidden">
                                @foreach ($items as $product)
                                    <div class="p-4 flex gap-3 items-start">
                                        {{-- Imagem --}}
                                        @if ($product->image)
                                            <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}"
                                                class="w-16 h-16 rounded-xl object-cover flex-shrink-0">
                                        @else
                                            <div class="w-16 h-16 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0 text-2xl">
                                                🍽️
                                            </div>
                                        @endif

                                        {{-- Info + form --}}
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-gray-800 text-sm leading-tight">{{ $product->name }}</p>
                                            @if ($product->description)
                                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $product->description }}</p>
                                            @endif
                                            <p class="text-indigo-700 font-bold text-sm mt-1">
                                                R$ {{ number_format($product->amount, 2, ',', '.') }}
                                            </p>

                                            <form method="POST" action="{{ route('table.add-item', $table->uuid) }}"
                                                class="flex items-center gap-2 mt-2">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                                <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden">
                                                    <button type="button"
                                                        onclick="changeQty(this, -1)"
                                                        class="px-3 py-1.5 text-gray-500 hover:bg-gray-50 text-lg leading-none">−</button>
                                                    <input type="number" name="quantity" value="1" min="1" max="99"
                                                        class="w-10 text-center text-sm border-none outline-none py-1.5 bg-transparent">
                                                    <button type="button"
                                                        onclick="changeQty(this, 1)"
                                                        class="px-3 py-1.5 text-gray-500 hover:bg-gray-50 text-lg leading-none">+</button>
                                                </div>
                                                <button type="submit"
                                                    class="flex-1 bg-indigo-600 text-white text-sm font-medium py-1.5 px-3 rounded-xl hover:bg-indigo-700 transition-colors">
                                                    Adicionar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- ABA: CONSUMO --}}
            <div id="content-consumo" class="tab-content">

                {{-- Cliente --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400">Mesa aberta desde {{ $session->opened_at->format('H:i') }}</p>
                        @if ($session->client || $session->guest_name)
                            <p class="text-sm font-medium text-gray-800">{{ $session->client_display_name }}</p>
                        @endif
                    </div>
                    <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">● Aberta</span>
                </div>

                {{-- Itens --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-50">
                        <h2 class="font-semibold text-gray-800 text-sm">Itens do pedido</h2>
                    </div>

                    @if ($session->items->isEmpty())
                        <div class="px-5 py-8 text-center text-sm text-gray-400">
                            Nenhum item adicionado ainda.<br>
                            <button onclick="switchTab('cardapio')" class="mt-2 text-indigo-500 underline text-xs">
                                Ver cardápio
                            </button>
                        </div>
                    @else
                        <ul class="divide-y divide-gray-50">
                            @foreach ($session->items as $item)
                                <li class="px-5 py-3 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">{{ $item->product_name }}</p>
                                        <p class="text-xs text-gray-400">
                                            {{ $item->quantity }}x R$ {{ number_format($item->unit_price, 2, ',', '.') }}
                                        </p>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-800">
                                        R$ {{ number_format($item->total_amount, 2, ',', '.') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="px-5 py-4 bg-gray-50 flex justify-between items-center">
                            <span class="font-semibold text-gray-700 text-sm">Total</span>
                            <span class="text-lg font-bold text-indigo-700">
                                R$ {{ number_format($session->items->sum('total_amount'), 2, ',', '.') }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>

        @else
            {{-- Mesa livre --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="text-4xl mb-3">🪑</div>
                <h2 class="font-semibold text-gray-700 mb-1">Mesa livre</h2>
                <p class="text-sm text-gray-400">Não há consumo ativo nesta mesa no momento.</p>
            </div>
        @endif

        <p class="text-center text-xs text-gray-400 mt-8">Powered by Comere</p>
    </div>

    <script>
        function switchTab(tab) {
            ['cardapio', 'consumo'].forEach(function(t) {
                document.getElementById('tab-' + t).classList.remove('active');
                document.getElementById('content-' + t).classList.remove('active');
            });
            document.getElementById('tab-' + tab).classList.add('active');
            document.getElementById('content-' + tab).classList.add('active');
        }

        function changeQty(btn, delta) {
            var input = btn.parentElement.querySelector('input[type=number]');
            var val = parseInt(input.value) + delta;
            if (val >= 1 && val <= 99) input.value = val;
        }
    </script>

</body>
</html>
