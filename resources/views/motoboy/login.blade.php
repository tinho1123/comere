<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — Motoboy Comere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background: #f8fafc; font-family: system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center py-8 px-4">
    <div class="w-full max-w-sm">
        <h1 class="text-xl font-bold text-gray-900 mb-1 text-center">Painel do motoboy</h1>
        <p class="text-sm text-gray-500 mb-6 text-center">Entre para ver suas entregas e convites de lojas.</p>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl p-4 mb-4">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('motoboy.login') }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
            @csrf

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">Telefone</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required autofocus
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700 block mb-1">Senha</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" class="rounded border-gray-300">
                Manter conectado
            </label>

            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 mt-2">
                Entrar
            </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
            Ainda não tem conta? <a href="{{ route('motoboy.register.show') }}" class="font-bold text-red-500">Cadastre-se</a>
        </p>
    </div>
</body>
</html>
