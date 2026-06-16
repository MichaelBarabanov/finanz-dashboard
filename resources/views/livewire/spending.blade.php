<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Ausgaben</h1>
            <p class="text-sm text-dim">Wohin das Geld geflossen ist · {{ $this->periodLabel() }}</p>
        </div>
        <div class="flex flex-wrap gap-1">
            @foreach (['current' => 'Monat', '3m' => '3 M', '6m' => '6 M', '12m' => '12 M', 'all' => 'Alles'] as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                        class="nav-link {{ $period === $key ? 'active' : '' }} text-sm">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Gesamt --}}
    <div class="glass p-5">
        <div class="text-[11px] uppercase tracking-widest text-dim">Ausgaben gesamt ({{ $this->periodLabel() }})</div>
        <div class="mt-1 text-3xl font-semibold kpi neon neg">{{ money_format_de(abs($grandTotalCents)) }}</div>
    </div>

    {{-- Gesamt nach Kategorie --}}
    @if ($overall->isNotEmpty())
        <div class="glass p-5">
            <h2 class="mb-4 text-sm font-semibold">Nach Kategorie (alle Konten)</h2>
            @php $maxOverall = abs($overall->first()['amount_cents'] ?: 1); @endphp
            <div class="space-y-2">
                @foreach ($overall as $c)
                    @php $val = abs($c['amount_cents']); $pct = $maxOverall ? round($val / $maxOverall * 100) : 0; @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $c['color'] }}; box-shadow:0 0 10px {{ $c['color'] }}66"></span>
                                {{ $c['category'] }}
                            </span>
                            <span class="kpi neg">{{ money_format_de($val) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full" style="background: var(--brd)">
                            <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $c['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Getrennt nach Konto --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @forelse ($byAccount as $acc)
            <div class="glass p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">{{ $acc['name'] }} <span class="text-dim">· {{ $acc['type'] === 'credit_card' ? 'Karte' : 'Giro' }}</span></h2>
                    <span class="kpi neg font-semibold">{{ money_format_de(abs($acc['total_cents'])) }}</span>
                </div>
                @php $maxAcc = abs($acc['categories']->first()['amount_cents'] ?: 1); @endphp
                <div class="space-y-2">
                    @foreach ($acc['categories'] as $c)
                        @php $val = abs($c['amount_cents']); $pct = $maxAcc ? round($val / $maxAcc * 100) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full" style="background: {{ $c['color'] }}"></span>
                                    {{ $c['category'] }} <span class="text-xs text-dim">· {{ $c['cnt'] }}×</span>
                                </span>
                                <span class="kpi">{{ money_format_de($val) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full" style="background: var(--brd)">
                                <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $c['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="glass p-5 text-sm text-dim lg:col-span-2">Keine Ausgaben im gewählten Zeitraum. Importiere zuerst Auszüge.</div>
        @endforelse
    </div>
</div>
