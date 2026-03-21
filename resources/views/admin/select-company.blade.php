<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Selecionar loja — Painel do administrador</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background-color: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white">Painel do administrador</h1>
            <p class="text-gray-400 mt-2 text-sm">Selecione a loja para continuar</p>
        </div>

        <div class="bg-gray-900 border border-white/10 rounded-2xl shadow-xl p-6">
            <form method="POST" action="{{ route('admin.select-company.store') }}">
                @csrf

                <div class="flex flex-col gap-3 mb-6">
                    @foreach ($companies as $company)
                        <label class="flex items-center gap-4 rounded-xl border border-white/10 px-4 py-3 cursor-pointer hover:bg-white/5 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-500/10 transition">
                            <input
                                type="radio"
                                name="company_uuid"
                                value="{{ $company->uuid }}"
                                class="accent-indigo-500 shrink-0"
                                @if ($loop->first) checked @endif
                            >

                            {{-- Logo --}}
                            @if ($company->logo_path)
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}"
                                    alt="{{ $company->name }}"
                                    class="h-10 w-10 rounded-lg object-cover shrink-0 bg-white/10"
                                >
                            @else
                                <div class="h-10 w-10 rounded-lg bg-indigo-600/20 flex items-center justify-center shrink-0">
                                    <span class="text-indigo-400 font-bold text-sm">
                                        {{ mb_strtoupper(mb_substr($company->name, 0, 2)) }}
                                    </span>
                                </div>
                            @endif

                            {{-- Nome --}}
                            <span class="text-white text-sm font-medium leading-tight">{{ $company->name }}</span>
                        </label>
                    @endforeach
                </div>

                @error('company_uuid')
                    <p class="text-red-400 text-sm mb-4">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 text-sm transition"
                >
                    Entrar na loja
                </button>
            </form>
        </div>
    </div>
</body>
</html>
