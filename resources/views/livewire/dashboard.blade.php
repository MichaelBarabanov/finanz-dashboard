<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Übersicht</h1>
        <p class="text-sm text-dim">Stand {{ now()->format('d.m.Y') }} · {{ $monthLabel }}</p>
    </div>

    @if (session('saved'))
        <div class="glass mb-6 px-4 py-3 text-sm pos">{{ session('saved') }}</div>
    @endif

    {{-- KPI-Kacheln --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="glass p-5">
            <div class="text-[11px] uppercase tracking-widest text-dim">Gesamtsaldo</div>
            <div class="mt-1 text-2xl font-semibold kpi neon {{ $totalBalance < 0 ? 'neg' : 'text-accent' }}">{{ money_format_de($totalBalance) }}</div>
        </div>
        <div class="glass p-5">
            <div class="text-[11px] uppercase tracking-widest text-dim">Einnahmen ({{ $monthLabel }})</div>
            <div class="mt-1 text-2xl font-semibold kpi pos">{{ money_format_de($incomeCents) }}</div>
        </div>
        <div class="glass p-5">
            <div class="text-[11px] uppercase tracking-widest text-dim">Ausgaben ({{ $monthLabel }})</div>
            <div class="mt-1 text-2xl font-semibold kpi neg">{{ money_format_de(abs($expenseCents)) }}</div>
        </div>
        <div class="glass p-5">
            <div class="text-[11px] uppercase tracking-widest text-dim">Saldo des Monats</div>
            <div class="mt-1 text-2xl font-semibold kpi {{ $netCents < 0 ? 'neg' : 'pos' }}">{{ money_format_de($netCents) }}</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="glass p-5 lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold">Einnahmen vs. Ausgaben <span class="text-dim">· 6 Monate</span></h2>
            <canvas id="chartIncomeExpense" height="120"></canvas>
        </div>

        <div class="glass p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Konten</h2>
                <a href="{{ route('accounts') }}" wire:navigate class="text-xs link">verwalten</a>
            </div>
            @forelse ($accounts as $account)
                <div class="flex items-center justify-between border-b divider py-2 last:border-0">
                    <div>
                        <div class="text-sm font-medium">{{ $account->name }}</div>
                        <div class="text-xs text-dim">{{ $account->isCreditCard() ? 'Kreditkarte' : 'Girokonto' }}{{ $account->bank ? ' · '.$account->bank : '' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold kpi {{ $account->balance_cents < 0 ? 'neg' : '' }}">{{ money_format_de($account->balance_cents) }}</div>
                        @if ($account->isCreditCard() && $account->available_credit_cents !== null)
                            <div class="text-xs text-dim">frei: {{ money_format_de($account->available_credit_cents) }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-dim">Noch keine Konten. <a href="{{ route('accounts') }}" wire:navigate class="link">Jetzt anlegen</a>.</p>
            @endforelse
        </div>
    </div>

    @if (! empty($cardSeries))
        <div class="mt-6 glass p-5">
            <h2 class="mb-4 text-sm font-semibold">Kreditkarten-Verlauf <span class="text-dim">· Saldo, 6 Monate</span></h2>
            <canvas id="chartCard" height="90"></canvas>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="glass p-5">
            <h2 class="mb-4 text-sm font-semibold">Top-Ausgaben nach Kategorie <span class="text-dim">· {{ $monthLabel }}</span></h2>
            @forelse ($topCategories as $row)
                <div class="flex items-center justify-between py-1.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $row->category->color ?? '#94a3b8' }}; box-shadow:0 0 10px {{ $row->category->color ?? '#94a3b8' }}66"></span>
                        <span class="text-sm">{{ $row->category->name ?? 'Unkategorisiert' }}</span>
                    </div>
                    <span class="text-sm font-medium kpi neg">{{ money_format_de(abs((int) $row->sum_cents)) }}</span>
                </div>
            @empty
                <p class="text-sm text-dim">Noch keine kategorisierten Ausgaben diesen Monat.</p>
            @endforelse
        </div>

        <div class="glass p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Letzte Buchungen</h2>
                <a href="{{ route('transactions') }}" wire:navigate class="text-xs link">alle</a>
            </div>
            @forelse ($recent as $t)
                <div class="flex items-center justify-between border-b divider py-2 last:border-0">
                    <div>
                        <div class="text-sm">{{ $t->counterparty ?: ($t->description ?: '—') }}</div>
                        <div class="text-xs text-dim">{{ $t->booking_date->format('d.m.Y') }} · {{ $t->account->name ?? '' }}</div>
                    </div>
                    <span class="text-sm font-medium kpi {{ $t->amount_cents < 0 ? 'neg' : 'pos' }}">{{ money_format_de($t->amount_cents) }}</span>
                </div>
            @empty
                <p class="text-sm text-dim">Noch keine Buchungen. <a href="{{ route('transactions') }}" wire:navigate class="link">Erste erfassen</a>.</p>
            @endforelse
        </div>
    </div>

    {{-- Schulden-Überblick --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="glass p-5">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Schulden</h2>
                <a href="{{ route('debts') }}" wire:navigate class="text-xs link">Details</a>
            </div>
            <div class="text-2xl font-semibold kpi neg neon">{{ money_format_de($totalDebtCents) }}</div>
            <div class="text-xs text-dim">offen über alle aktiven Schulden</div>
        </div>
        <div class="glass p-5 lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold">Fällige Raten <span class="text-dim">· nächste 14 Tage</span></h2>
            @forelse ($upcomingPayments as $p)
                <div class="flex items-center justify-between border-b divider py-1.5 last:border-0">
                    <div>
                        <span class="text-sm">{{ $p->debt->name ?? '—' }}</span>
                        <span class="text-xs text-dim">· fällig {{ $p->due_date->format('d.m.Y') }}</span>
                    </div>
                    <span class="text-sm font-medium kpi neg">{{ money_format_de($p->amount_cents) }}</span>
                </div>
            @empty
                <p class="text-sm text-dim">Keine Raten in den nächsten 14 Tagen fällig.</p>
            @endforelse
        </div>
    </div>

    <script>
        window.initIncomeExpenseChart = function () {
            const el = document.getElementById('chartIncomeExpense');
            if (!el || typeof Chart === 'undefined') return;
            if (el._chart) { el._chart.destroy(); }
            const dark = document.documentElement.classList.contains('dark');
            const tick = dark ? '#93a0b5' : '#5b677a';
            const grid = dark ? 'rgba(148,163,184,0.12)' : 'rgba(15,23,42,0.08)';
            el._chart = new Chart(el, {
                type: 'bar',
                data: {
                    labels: @json($chartLabels),
                    datasets: [
                        { label: 'Einnahmen', data: @json($chartIncome), backgroundColor: 'rgba(52,211,153,0.85)', borderRadius: 6 },
                        { label: 'Ausgaben', data: @json($chartExpense), backgroundColor: 'rgba(251,113,133,0.85)', borderRadius: 6 },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom', labels: { color: tick, usePointStyle: true } } },
                    scales: {
                        x: { ticks: { color: tick }, grid: { color: grid } },
                        y: { beginAtZero: true, ticks: { color: tick, callback: v => v.toLocaleString('de-DE') + ' €' }, grid: { color: grid } },
                    },
                },
            });
        };
        window.initCardChart = function () {
            const el = document.getElementById('chartCard');
            if (!el || typeof Chart === 'undefined') return;
            if (el._chart) { el._chart.destroy(); }
            const dark = document.documentElement.classList.contains('dark');
            const tick = dark ? '#93a0b5' : '#5b677a';
            const grid = dark ? 'rgba(148,163,184,0.12)' : 'rgba(15,23,42,0.08)';
            const accent = dark ? '#22d3ee' : '#0891b2';
            el._chart = new Chart(el, {
                type: 'line',
                data: {
                    labels: @json($cardLabels ?? []),
                    datasets: [{
                        label: 'Kartensaldo', data: @json($cardSeries ?? []),
                        borderColor: accent, backgroundColor: 'rgba(34,211,238,0.12)',
                        fill: true, tension: 0.3, pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: tick }, grid: { color: grid } },
                        y: { ticks: { color: tick, callback: v => v.toLocaleString('de-DE') + ' €' }, grid: { color: grid } },
                    },
                },
            });
        };
        function initAllCharts() { window.initIncomeExpenseChart(); window.initCardChart(); }
        initAllCharts();
        document.addEventListener('livewire:navigated', initAllCharts);
        window.addEventListener('theme-changed', initAllCharts);
    </script>
</div>
