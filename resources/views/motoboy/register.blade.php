<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de motoboy — Comere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background: #f8fafc; font-family: system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center py-8 px-4">
    <div class="w-full max-w-sm">
        <h1 class="text-xl font-bold text-gray-900 mb-1 text-center">Cadastro de motoboy</h1>
        <p class="text-sm text-gray-500 mb-6 text-center">Crie sua conta para receber e aceitar entregas das lojas parceiras.</p>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl p-4 mb-4">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('motoboy.register') }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
            @csrf

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">Nome completo</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">Telefone (WhatsApp)</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="(11) 91234-5678"
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">CPF (opcional)</label>
                <input type="text" name="cpf" value="{{ old('cpf') }}"
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">Veículo</label>
                <select name="vehicle_type" required class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
                    <option value="motoboy" @selected(old('vehicle_type') === 'motoboy')>Moto</option>
                    <option value="car" @selected(old('vehicle_type') === 'car')>Carro</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">Senha</label>
                <input type="password" name="password" required minlength="6"
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">Confirmar senha</label>
                <input type="password" name="password_confirmation" required minlength="6"
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
            </div>

            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 mt-2">
                Criar conta
            </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
            Já tem conta? <a href="{{ route('motoboy.login.show') }}" class="font-bold text-red-500">Entrar</a>
        </p>
    </div>
</body>
</html>
