<div>
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Konten</h1>

    @if (session('saved'))
        <div class="mb-4 glass px-4 py-2 text-sm pos">{{ session('saved') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Formular --}}
        <div class="glass p-5">
            <h2 class="mb-4 text-sm font-semibold">{{ $editingId ? 'Konto bearbeiten' : 'Neues Konto' }}</h2>
            <form wire:submit="save" class="space-y-3">
                <div>
                    <label class="block text-xs text-dim">Name</label>
                    <input type="text" wire:model="name" placeholder="z.B. Sparkasse Giro" class="field mt-1">
                    @error('name') <span class="text-xs neg">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs text-dim">Typ</label>
                    <select wire:model.live="type" class="field mt-1">
                        <option value="giro">Girokonto</option>
                        <option value="credit_card">Kreditkarte</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-dim">Bank</label>
                    <input type="text" wire:model="bank" placeholder="z.B. Sparkasse / Hanseatic" class="field mt-1">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-dim">IBAN (letzte 4)</label>
                        <input type="text" wire:model="iban_last4" maxlength="4" placeholder="1234" class="field mt-1">
                    </div>
                    <div>
                        <label class="block text-xs text-dim">Anfangssaldo (€)</label>
                        <input type="text" wire:model="opening_balance" placeholder="0,00" class="field mt-1">
                    </div>
                </div>
                @if ($type === 'credit_card')
                    <div>
                        <label class="block text-xs text-dim">Kreditlimit (€)</label>
                        <input type="text" wire:model="credit_limit" placeholder="z.B. 2000,00" class="field mt-1">
                    </div>
                @endif
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn btn-accent">{{ $editingId ? 'Speichern' : 'Anlegen' }}</button>
                    @if ($editingId)
                        <button type="button" wire:click="resetForm" class="btn btn-soft">Abbrechen</button>
                    @endif
                </div>
            </form>
        </div>

        {{-- Liste --}}
        <div class="glass p-5 lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold">Vorhandene Konten</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b divider text-left text-xs uppercase tracking-wide text-dim">
                            <th class="py-2">Name</th>
                            <th class="py-2">Typ</th>
                            <th class="py-2 text-right">Saldo</th>
                            <th class="py-2 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr class="border-b divider">
                                <td class="py-2">
                                    <div class="font-medium">{{ $account->name }}</div>
                                    <div class="text-xs text-dim">{{ $account->bank }}{{ $account->iban_last4 ? ' ····'.$account->iban_last4 : '' }}</div>
                                </td>
                                <td class="py-2 text-dim">{{ $account->isCreditCard() ? 'Kreditkarte' : 'Giro' }}</td>
                                <td class="py-2 text-right font-semibold kpi {{ $account->balance_cents < 0 ? 'neg' : '' }}">{{ money_format_de($account->balance_cents) }}</td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button wire:click="edit({{ $account->id }})" class="text-xs link">bearbeiten</button>
                                    <button wire:click="delete({{ $account->id }})" wire:confirm="Konto inkl. aller Buchungen löschen?" class="ml-2 text-xs neg hover:underline">löschen</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-dim">Noch keine Konten angelegt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
