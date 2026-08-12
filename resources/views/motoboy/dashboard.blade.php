<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas entregas — Motoboy Comere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background: #f8fafc; font-family: system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen py-8 px-4">
    <div class="w-full max-w-md mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Olá,</p>
                <h1 class="text-xl font-bold text-gray-900">{{ $driver->name }}</h1>
            </div>
            <form method="POST" action="{{ route('motoboy.logout') }}">
                @csrf
                <button type="submit" class="text-sm font-bold text-gray-400 hover:text-red-500">Sair</button>
            </form>
        </div>

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-bold rounded-xl px-4 py-3 mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if ($activeDeliveries->isNotEmpty())
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Entregas em andamento</h2>
            <div class="flex flex-col gap-3 mb-8">
                @foreach ($activeDeliveries as $delivery)
                    <a
                        href="{{ route('delivery.tracking.show', $delivery->tracking_token) }}"
                        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between hover:border-red-300 transition-colors"
                    >
                        <div>
                            <p class="font-bold text-gray-900">{{ $delivery->company->name }}</p>
                            <p class="text-xs text-gray-400">Pedido #{{ strtoupper(substr($delivery->order->uuid, 0, 8)) }}</p>
                        </div>
                        <span class="text-xs font-bold text-red-500">Abrir →</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($pendingInvites->isNotEmpty())
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Convites de lojas</h2>
            <div class="flex flex-col gap-3 mb-8">
                @foreach ($pendingInvites as $company)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                        <p class="font-bold text-gray-900">{{ $company->name }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            Taxa por entrega: <span class="font-bold text-gray-700">R$ {{ number_format($company->pivot->delivery_fee, 2, ',', '.') }}</span>
                        </p>
                        <div class="flex gap-2 mt-3">
                            <form method="POST" action="{{ route('motoboy.invite.accept', $company->pivot->id) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white text-sm font-bold py-2 rounded-lg transition-colors">
                                    Aceitar
                                </button>
                            </form>
                            <form method="POST" action="{{ route('motoboy.invite.reject', $company->pivot->id) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-bold py-2 rounded-lg transition-colors">
                                    Recusar
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Lojas vinculadas</h2>
        @if ($acceptedCompanies->isEmpty())
            <p class="text-sm text-gray-400">Nenhuma loja vinculada ainda. Peça para a loja te convidar pelo seu telefone cadastrado.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($acceptedCompanies as $company)
                    <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center justify-between">
                        <span class="font-medium text-gray-800 text-sm">{{ $company->name }}</span>
                        <span class="text-xs font-bold text-gray-400">R$ {{ number_format($company->pivot->delivery_fee, 2, ',', '.') }}/entrega</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
