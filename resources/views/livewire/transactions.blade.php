<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight">Korrektur</h1>
        <p class="text-sm text-dim">Importierte Buchungen prüfen &amp; kategorisieren</p>
    </div>

    @if (session('saved'))
        <div class="mb-4 glass px-4 py-2 text-sm pos">{{ session('saved') }}</div>
    @endif

    @if ($learn)
        <div class="mb-4 glass flex flex-wrap items-center gap-2 px-4 py-3 text-sm">
            <span>Künftig Buchungen mit</span>
            <input wire:model="learnPattern" class="field w-36 py-0.5 text-xs">
            <span>automatisch als <strong class="text-accent">{{ $learn['category_name'] }}</strong> einordnen? <span class="text-xs text-dim">(wirkt sofort rückwirkend)</span></span>
            <button wire:click="confirmLearn" class="btn btn-accent py-1 text-xs">Regel anlegen</button>
            <button wire:click="dismissLearn" class="btn btn-ghost text-xs">nein, danke</button>
        </div>
    @endif

    {{-- Filter --}}
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <select wire:model.live="filterAccount" class="field w-auto">
            <option value="">Alle Konten</option>
            @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach
        </select>
        <input type="month" wire:model.live="filterMonth" class="field w-auto">
        <button wire:click="$set('filterMonth', '')" class="btn btn-ghost text-xs">Monatsfilter zurücksetzen</button>
    </div>

    {{-- Tabelle --}}
    <div class="glass p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b divider text-left text-xs uppercase tracking-wide text-dim">
                        <th class="py-2">Datum</th>
                        <th class="py-2">Empfänger / Zweck</th>
                        <th class="py-2">Konto</th>
                        <th class="py-2">Kategorie</th>
                        <th class="py-2 text-right">Betrag</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $t)
                        <tr class="border-b divider" wire:key="tx-{{ $t->id }}">
                            <td class="py-2 whitespace-nowrap text-dim">{{ $t->booking_date->format('d.m.Y') }}</td>
                            <td class="py-2">
                                <div>{{ $t->counterparty ?: '—' }}</div>
                                @if ($t->description)<div class="text-xs text-dim">{{ \Illuminate\Support\Str::limit($t->description, 60) }}</div>@endif
                                @if ($t->is_internal_transfer)<span class="chip">interner Transfer</span>@endif
                            </td>
                            <td class="py-2 text-dim">{{ $t->account->name ?? '' }}</td>
                            <td class="py-2">
                                <select wire:change="setCategory({{ $t->id }}, $event.target.value)" class="field w-40 py-1 text-xs">
                                    <option value="">– keine –</option>
                                    @foreach ($categories as $category)<option value="{{ $category->id }}" @selected($t->category_id === $category->id)>{{ $category->name }}</option>@endforeach
                                </select>
                            </td>
                            <td class="py-2 text-right font-semibold kpi {{ $t->amount_cents < 0 ? 'neg' : 'pos' }}">{{ money_format_de($t->amount_cents) }}</td>
                            <td class="py-2 text-right">
                                <button wire:click="delete({{ $t->id }})" wire:confirm="Buchung löschen?" class="text-xs neg hover:underline">löschen</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-dim">Keine Buchungen für diesen Filter. Importiere zuerst einen Auszug.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</div>
