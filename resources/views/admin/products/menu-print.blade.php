<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio — {{ $company->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap">
    <style>
        :root {
            --ink: #1f2937;
            --ink-soft: #6b7280;
            --line: #e5e7eb;
            --accent: #4f46e5;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 2.5rem 2rem 4rem;
            background: #f3f4f6;
            color: var(--ink);
            font-family: "Inter", system-ui, sans-serif;
        }
        .toolbar {
            max-width: 720px;
            margin: 0 auto 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
        }
        .toolbar button {
            font-family: inherit;
            font-size: .875rem;
            font-weight: 600;
            padding: .6rem 1.1rem;
            border-radius: 8px;
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #fff;
            cursor: pointer;
        }
        .toolbar button:hover { background: #4338ca; }

        .menu {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 12px 32px -16px rgba(0,0,0,.15);
            padding: 3rem 3rem 3.5rem;
        }

        .menu-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding-bottom: 1.75rem;
            border-bottom: 2px solid var(--ink);
            margin-bottom: 2rem;
        }
        .menu-header img {
            width: 84px;
            height: 84px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: .9rem;
            border: 1px solid var(--line);
        }
        .menu-header h1 {
            font-family: "Fraunces", Georgia, serif;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 .3rem;
        }
        .menu-header p {
            margin: 0;
            font-size: .82rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .category {
            margin-bottom: 2.1rem;
        }
        .category:last-child { margin-bottom: 0; }
        .category h2 {
            font-family: "Fraunces", Georgia, serif;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--accent);
            margin: 0 0 1rem;
            padding-bottom: .4rem;
            border-bottom: 1px solid var(--line);
        }

        .item {
            display: flex;
            align-items: baseline;
            gap: .5rem;
            margin-bottom: 1rem;
        }
        .item:last-child { margin-bottom: 0; }
        .item-name {
            font-weight: 600;
            font-size: .98rem;
            white-space: nowrap;
        }
        .item-leader {
            flex: 1;
            border-bottom: 1px dotted #cbd5e1;
            transform: translateY(-3px);
        }
        .item-price {
            font-weight: 700;
            font-size: .98rem;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .item-price .original {
            font-weight: 400;
            color: var(--ink-soft);
            text-decoration: line-through;
            margin-right: .4rem;
            font-size: .85em;
        }
        .item-description {
            display: block;
            font-weight: 400;
            font-size: .82rem;
            color: var(--ink-soft);
            white-space: normal;
            margin-top: .15rem;
        }
        .item-body { min-width: 0; }

        .menu-footer {
            margin-top: 2.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            text-align: center;
            font-size: .75rem;
            color: var(--ink-soft);
        }

        .empty {
            text-align: center;
            color: var(--ink-soft);
            padding: 2rem 0;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .menu { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="menu">
        <div class="menu-header">
            @if ($company->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="Logo {{ $company->name }}">
            @endif
            <h1>{{ $company->name }}</h1>
            <p>Cardápio</p>
        </div>

        @forelse ($categories as $categoryName => $products)
            <div class="category">
                <h2>{{ $categoryName }}</h2>

                @foreach ($products as $product)
                    <div class="item">
                        <div class="item-body">
                            <span class="item-name">{{ $product->name }}</span>
                            @if ($product->description)
                                <span class="item-description">{{ $product->description }}</span>
                            @endif
                        </div>
                        <div class="item-leader"></div>
                        <div class="item-price">
                            @if ((float) $product->discounts > 0)
                                <span class="original">R$ {{ number_format((float) $product->amount, 2, ',', '.') }}</span>
                            @endif
                            R$ {{ number_format((float) $product->total_amount, 2, ',', '.') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="empty">Nenhum produto ativo cadastrado ainda.</p>
        @endforelse

        <div class="menu-footer">
            Cardápio gerado por Comere — {{ now()->format('d/m/Y') }}
        </div>
    </div>
</body>
</html>
