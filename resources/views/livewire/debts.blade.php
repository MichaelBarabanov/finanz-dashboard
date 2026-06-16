<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight">Schulden &amp; Raten</h1>
        <p class="text-sm text-dim">Stand {{ now()->format('d.m.Y') }}</p>
    </div>

    @if (session('saved'))
        <div class="glass px-4 py-3 text-sm pos">{{ session('saved') }}</div>
    @endif

    {{-- KPI-Zeile --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="glass p-5">
            <div class="text-[11px] uppercase tracking-widest text-dim">Gesamtschulden (offen)</div>
            <div class="mt-1 text-2xl font-semibold kpi neon neg">{{ money_format_de($totalRemainingCents) }}</div>
        </div>
        <div class="glass p-5">
            <div class="text-[11px] uppercase tracking-widest text-dim">Monatliche Belastung</div>
            <div class="mt-1 text-2xl font-semibold kpi">{{ money_format_de($totalMonthlyCents) }}</div>
        </div>
        <div class="glass p-5">
            <div class="text-[11px] uppercase tracking-widest text-dim">Bereits getilgt</div>
            <div class="mt-1 text-2xl font-semibold kpi pos">{{ money_format_de($totalPaidCents) }}</div>
        </div>
    </div>

    {{-- Schulden-Karten --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @forelse ($debts as $debt)
            <div class="glass p-5 {{ $debt->status !== 'active' ? 'opacity-60' : '' }}">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-semibold">{{ $debt->name }}</div>
                        <div class="text-xs text-dim">
                            {{ $debt->isInstallment() ? 'Feste Raten' : 'Flexible Tilgung' }}
                            · Abbuchung am {{ $debt->payment_day }}.
                            @if ($debt->linkedAccount) · {{ $debt->linkedAccount->name }} @endif
                        </div>
                    </div>
                    <button wire:click="deleteDebt({{ $debt->id }})" wire:confirm="Schuld löschen?" class="text-xs neg hover:underline">löschen</button>
                </div>

                {{-- Fortschritt --}}
                <div class="mt-4">
                    <div class="flex items-end justify-between text-sm">
                        <span class="kpi neg text-lg font-semibold">{{ money_format_de($debt->remaining_cents) }}</span>
                        <span class="text-xs text-dim">von {{ money_format_de($debt->total_amount_cents) }}</span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full" style="background: var(--brd)">
                        <div class="h-full rounded-full" style="width: {{ $debt->progress_percent }}%; background: linear-gradient(90deg, var(--accent), var(--accent2)); box-shadow: 0 0 12px var(--glow)"></div>
                    </div>
                    <div class="mt-1 flex justify-between text-xs text-dim">
                        <span>{{ $debt->progress_percent }}% getilgt</span>
                        @if ($debt->isInstallment())
                            <span>{{ $debt->installments_remaining }} Raten offen{{ $debt->projected_end ? ' · fertig '.$debt->projected_end->format('m/Y') : '' }}</span>
                        @elseif ($debt->next_payment)
                            <span>nächste Fälligkeit {{ $debt->next_payment->due_date->format('d.m.Y') }}</span>
                        @endif
                    </div>
                </div>

                {{-- installment: Ratenplan --}}
                @if ($debt->isInstallment())
                    <div class="mt-4 max-h-52 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="border-b divider text-left uppercase tracking-wide text-dim">
                                <th class="py-1">Fällig</th><th class="py-1 text-right">Rate</th><th class="py-1 text-right">Status</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($debt->payments as $p)
                                    <tr class="border-b divider">
                                        <td class="py-1">{{ $p->due_date->format('d.m.Y') }}</td>
                                        <td class="py-1 text-right kpi">{{ money_format_de($p->amount_cents) }}</td>
                                        <td class="py-1 text-right">
                                            <button wire:click="togglePaid({{ $p->id }})"
                                                    class="chip {{ $p->paid ? '' : '' }}"
                                                    style="{{ $p->paid ? 'color:var(--pos);border-color:var(--pos)' : '' }}">
                                                {{ $p->paid ? '✓ bezahlt' : 'offen' }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- revolving: Tilgung erfassen --}}
                    <div class="mt-4">
                        @if ($payDebtId === $debt->id)
                            <div class="flex flex-wrap items-end gap-2">
                                <div><label class="block text-xs text-dim">Betrag (€)</label><input wire:model="payAmount" placeholder="z.B. 50,00" class="field w-28 py-1 text-xs"></div>
                                <div><label class="block text-xs text-dim">Datum</label><input type="date" wire:model="payDate" class="field w-auto py-1 text-xs"></div>
                                <button wire:click="recordPayment" class="btn btn-accent py-1 text-xs">Speichern</button>
                                <button wire:click="$set('payDebtId', null)" class="btn btn-ghost text-xs">Abbrechen</button>
                            </div>
                        @else
                            <button wire:click="startPayment({{ $debt->id }})" class="btn btn-soft text-xs">+ Tilgung erfassen</button>
                        @endif
                        @if ($debt->payments->where('paid', true)->count())
                            <div class="mt-3 text-xs text-dim">Letzte Tilgungen:</div>
                            <div class="mt-1 space-y-0.5">
                                @foreach ($debt->payments->where('paid', true)->sortByDesc('due_date')->take(4) as $p)
                                    <div class="flex justify-between text-xs">
                                        <span class="text-dim">{{ $p->due_date->format('d.m.Y') }}</span>
                                        <span class="kpi pos">{{ money_format_de($p->amount_cents) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="glass p-5 text-sm text-dim lg:col-span-2">Noch keine Schulden erfasst.</div>
        @endforelse
    </div>

    {{-- Neue Schuld --}}
    <div class="glass p-5">
        <h2 class="mb-4 text-sm font-semibold">Neue Schuld erfassen</h2>
        <form wire:submit="addDebt" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="block text-xs text-dim">Name</label>
                <input wire:model="name" placeholder="z.B. PayPal eBay" class="field mt-1">
                @error('name') <span class="text-xs neg">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-dim">Art</label>
                <select wire:model.live="type" class="field mt-1">
                    <option value="installment">Feste Raten</option>
                    <option value="revolving">Flexible Tilgung</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-dim">Gesamtbetrag (€)</label>
                <input wire:model="total_amount" placeholder="z.B. 600,00" class="field mt-1">
                @error('total_amount') <span class="text-xs neg">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-dim">Abbuchungstag</label>
                <input type="number" min="1" max="31" wire:model="payment_day" class="field mt-1">
            </div>
            @if ($type === 'installment')
                <div>
                    <label class="block text-xs text-dim">Anzahl Raten</label>
                    <input type="number" min="1" wire:model="installment_count" placeholder="z.B. 6" class="field mt-1">
                </div>
            @endif
            <div>
                <label class="block text-xs text-dim">Monatliche Rate (€)</label>
                <input wire:model="monthly_amount" placeholder="z.B. 100,00" class="field mt-1">
            </div>
            <div>
                <label class="block text-xs text-dim">Startdatum</label>
                <input type="date" wire:model="start_date" class="field mt-1">
            </div>
            <div>
                <label class="block text-xs text-dim">Verknüpftes Konto (optional)</label>
                <select wire:model="linked_account_id" class="field mt-1">
                    <option value="">– keins –</option>
                    @foreach ($accounts as $acc)<option value="{{ $acc->id }}">{{ $acc->name }}</option>@endforeach
                </select>
            </div>
            <div class="flex items-end lg:col-span-4">
                <button type="submit" class="btn btn-accent">Schuld anlegen</button>
                @if ($type === 'installment')
                    <span class="ml-3 self-center text-xs text-dim">Der Ratenplan wird automatisch generiert.</span>
                @endif
            </div>
        </form>
    </div>
</div>
