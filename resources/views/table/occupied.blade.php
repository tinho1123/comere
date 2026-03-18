<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $table->name }} — {{ $table->company->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background: #f8fafc; font-family: system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center px-4">

    <div class="w-full max-w-sm text-center">
        <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-8">
            <div class="text-5xl mb-4">🔒</div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Mesa ocupada</h1>
            <p class="text-sm text-gray-500 mb-1">
                <strong class="text-gray-700">{{ $table->name }}</strong> já está sendo usada por outro dispositivo.
            </p>
            <p class="text-sm text-gray-400">
                Somente o dispositivo que abriu a mesa pode fazer pedidos.
            </p>
        </div>

        <p class="text-xs text-gray-400 mt-6">
            {{ $table->company->name }} &middot; Powered by Comere
        </p>
    </div>

</body>
</html>
