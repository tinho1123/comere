<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mesa->name }} — {{ $mesa->company->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f8fafc; font-family: system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-start py-8 px-4">

    <div class="w-full max-w-md">

        {{-- Header da loja --}}
        <div class="text-center mb-6">
            <p class="text-sm text-gray-500">{{ $mesa->company->name }}</p>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $mesa->name }}</h1>
        </div>

        @if (session('success'))
            <div class="mb-4 bg-green-100 border border-green-300 text-green-800 rounded-xl px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($mesa->activeSession)
            @php $session = $mesa->activeSession; @endphp

            {{-- Status da mesa --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">● Mesa aberta</span>
                    <span class="text-xs text-gray-400">{{ $session->opened_at->format('d/m/Y H:i') }}</span>
                </div>

                @if ($session->client)
                    <p class="mt-2 text-sm text-gray-700">Cliente: <strong>{{ $session->client->name }}</strong></p>
                @elseif ($session->guest_name)
                    <p class="mt-2 text-sm text-gray-700">Cliente: <strong>{{ $session->guest_name }}</strong></p>
                @else
                    {{-- Formulário para registrar o nome --}}
                    <p class="mt-2 text-sm text-gray-500 mb-3">Nenhum cliente registrado nesta mesa.</p>
                    <form method="POST" action="{{ route('mesa.register-name', $mesa->uuid) }}" class="flex gap-2">
                        @csrf
                        <input
                            type="text"
                            name="guest_name"
                            placeholder="Seu nome"
                            required
                            class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        >
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-indigo-700">
                            Registrar
                        </button>
                    </form>
                    @error('guest_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            {{-- Itens consumidos --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-gray-50">
                    <h2 class="font-semibold text-gray-800">Consumo atual</h2>
                </div>

                @if ($session->items->isEmpty())
                    <div class="px-5 py-6 text-center text-sm text-gray-400">
                        Nenhum item adicionado ainda.
                    </div>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach ($session->items as $item)
                            <li class="px-5 py-3 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $item->product_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $item->quantity }}x R$ {{ number_format($item->unit_price, 2, ',', '.') }}</p>
                                </div>
                                <span class="text-sm font-semibold text-gray-800">
                                    R$ {{ number_format($item->total_amount, 2, ',', '.') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Total --}}
                    <div class="px-5 py-4 bg-gray-50 flex justify-between items-center">
                        <span class="font-semibold text-gray-700">Total</span>
                        <span class="text-lg font-bold text-indigo-700">
                            R$ {{ number_format($session->items->sum('total_amount'), 2, ',', '.') }}
                        </span>
                    </div>
                @endif
            </div>

        @else
            {{-- Mesa livre --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="text-4xl mb-3">🪑</div>
                <h2 class="font-semibold text-gray-700 mb-1">Mesa livre</h2>
                <p class="text-sm text-gray-400">Não há consumo ativo nesta mesa no momento.</p>
            </div>
        @endif

        <p class="text-center text-xs text-gray-400 mt-6">Powered by Comere</p>
    </div>

</body>
</html>
