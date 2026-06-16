<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Finanz Dashboard' }}</title>

    {{-- Theme aus localStorage anwenden. Standard: dunkel, sofern keine Wahl getroffen.
         Wird sowohl beim ersten Laden als auch nach jeder wire:navigate-Navigation
         aufgerufen, weil Livewire das <html>-Element neu morpht und die Klasse sonst
         verloren geht (= der "Dark-Mode-Reset beim Tab-Wechsel"-Bug). --}}
    <script>
        function applyStoredTheme() {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'light') { document.documentElement.classList.remove('dark'); }
                else { document.documentElement.classList.add('dark'); }
            } catch (e) { document.documentElement.classList.add('dark'); }
        }
        applyStoredTheme();
        document.addEventListener('livewire:navigated', applyStoredTheme);

        function toggleTheme() {
            var root = document.documentElement;
            root.classList.toggle('dark');
            try { localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light'); } catch (e) {}
            window.dispatchEvent(new Event('theme-changed'));
        }
    </script>

    {{-- Vorkompiliertes Tailwind (kein Runtime-JIT mehr -> deutlich schneller).
         Neu bauen nach Klassen-Änderungen: `npx tailwindcss -i resources/css/app.css -o public/css/app.css --minify` --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    {{-- ====================== Design-System: Dark Glass / Neon ====================== --}}
    <style>
        :root{
            --bg:#eef2f7; --grid:rgba(15,23,42,0.05);
            --g1:rgba(8,145,178,0.10); --g2:rgba(124,58,237,0.10);
            --panel:rgba(255,255,255,0.72); --brd:rgba(15,23,42,0.08);
            --text:#0f172a; --dim:#5b677a;
            --accent:#0891b2; --accent2:#7c3aed; --glow:rgba(8,145,178,0.22);
            --pos:#059669; --neg:#e11d48;
        }
        html.dark{
            --bg:#070b14; --grid:rgba(148,163,184,0.06);
            --g1:rgba(34,211,238,0.10); --g2:rgba(167,139,250,0.10);
            --panel:rgba(255,255,255,0.045); --brd:rgba(255,255,255,0.09);
            --text:#e6eaf2; --dim:#93a0b5;
            --accent:#22d3ee; --accent2:#a78bfa; --glow:rgba(34,211,238,0.20);
            --pos:#34d399; --neg:#fb7185;
        }
        body{
            background:
                radial-gradient(1000px 560px at 100% -12%, var(--g1), transparent 60%),
                radial-gradient(760px 520px at -10% 112%, var(--g2), transparent 60%),
                var(--bg);
            background-attachment: fixed; color: var(--text);
        }
        .grid-overlay{
            position:fixed; inset:0; pointer-events:none; z-index:0;
            background-image: linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px);
            background-size: 46px 46px;
            -webkit-mask-image: radial-gradient(ellipse at 50% -10%, #000 35%, transparent 78%);
            mask-image: radial-gradient(ellipse at 50% -10%, #000 35%, transparent 78%);
        }
        .glass{
            background: var(--panel); border:1px solid var(--brd); border-radius:1rem;
            backdrop-filter: blur(12px) saturate(125%); -webkit-backdrop-filter: blur(12px) saturate(125%);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 18px 40px -28px rgba(0,0,0,0.7);
        }
        .text-accent{ color: var(--accent); }
        .text-dim{ color: var(--dim); }
        .kpi{ font-variant-numeric: tabular-nums; letter-spacing:-0.02em; }
        .neon{ text-shadow: 0 0 22px var(--glow); }
        .pos{ color: var(--pos); } .neg{ color: var(--neg); }
        .divider{ border-color: var(--brd) !important; }
        .field{
            background: var(--panel); border:1px solid var(--brd); color:var(--text);
            border-radius:.6rem; padding:.5rem .65rem; font-size:.875rem; width:100%;
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
        }
        .field::placeholder{ color: var(--dim); opacity:.7; }
        .field:focus{ outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--glow); }
        .btn{ border-radius:.6rem; padding:.5rem .9rem; font-size:.875rem; font-weight:600; cursor:pointer; transition:.15s; display:inline-block; }
        .btn-accent{ color:#04121a; background:linear-gradient(135deg, var(--accent), var(--accent2)); box-shadow:0 10px 26px -12px var(--glow); }
        .btn-accent:hover{ filter:brightness(1.08); }
        .btn-accent:disabled{ opacity:.45; cursor:not-allowed; filter:none; }
        .btn-soft{ background:var(--panel); border:1px solid var(--brd); color:var(--text); }
        .btn-soft:hover{ border-color:var(--accent); }
        .btn-ghost{ color:var(--dim); } .btn-ghost:hover{ color:var(--text); }
        .chip{ border-radius:.5rem; padding:.1rem .4rem; font-size:10px; border:1px solid var(--brd); color:var(--dim); }
        .nav-link{ color:var(--dim); border-radius:.6rem; padding:.4rem .8rem; transition:.15s; position:relative; }
        .nav-link:hover{ color:var(--text); background:var(--panel); }
        .nav-link.active{ color:var(--text); background:var(--panel); box-shadow:0 0 0 1px var(--brd), 0 8px 22px -14px var(--glow); }
        .brand-dot{ background:linear-gradient(135deg,var(--accent),var(--accent2)); box-shadow:0 0 14px var(--glow); }
        a.link{ color:var(--accent); } a.link:hover{ text-decoration:underline; }
        ::-webkit-scrollbar{ height:10px; width:10px; } ::-webkit-scrollbar-thumb{ background:var(--brd); border-radius:8px; }
    </style>

    @livewireStyles
</head>
<body class="h-full antialiased">
    <div class="grid-overlay"></div>
    <div class="relative z-10 min-h-full">
        <header class="sticky top-0 z-20 border-b border-[var(--brd)] backdrop-blur-md">
            <div class="mx-auto max-w-6xl px-4">
                <div class="flex h-14 items-center justify-between">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 font-semibold tracking-tight">
                        <span class="h-5 w-5 rounded-md brand-dot"></span>
                        <span>Finanz<span class="text-accent">Dash</span></span>
                    </a>
                    <div class="flex items-center gap-2">
                        <nav class="flex gap-1 text-sm">
                            @php
                                $nav = [
                                    'dashboard'    => 'Übersicht',
                                    'accounts'     => 'Konten',
                                    'import'       => 'Import',
                                    'spending'     => 'Ausgaben',
                                    'debts'        => 'Schulden',
                                    'transactions' => 'Korrektur',
                                    'rules'        => 'Regeln',
                                ];
                            @endphp
                            @foreach ($nav as $route => $label)
                                <a href="{{ route($route) }}" wire:navigate
                                   class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}">{{ $label }}</a>
                            @endforeach
                        </nav>
                        <button type="button" onclick="toggleTheme()" title="Hell/Dunkel"
                                class="nav-link" aria-label="Theme umschalten">
                            <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">{{ $slot }}</main>

        <footer class="mx-auto max-w-6xl px-4 py-6 text-xs text-dim">
            Privates Finanz-Dashboard · lokal · {{ now()->format('d.m.Y') }}
        </footer>
    </div>

    @livewireScripts
</body>
</html>
