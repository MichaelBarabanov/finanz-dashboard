<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight">Import</h1>
        <p class="text-sm text-dim">Ein oder mehrere PDF-Auszüge einlesen</p>
    </div>

    @if (session('saved'))
        <div class="glass px-4 py-3 text-sm pos">{{ session('saved') }}</div>
    @endif
    @if (session('error'))
        <div class="glass px-4 py-3 text-sm neg" style="border-color:var(--neg)">{{ session('error') }}</div>
    @endif
    @if ($commitError)
        <div class="glass px-4 py-3 text-sm neg" style="border-color:var(--neg)">{{ $commitError }}</div>
    @endif

    @unless ($analyzed)
        <form wire:submit="analyze" class="glass space-y-4 p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium">Konto</label>
                    <select wire:model="account_id" class="field mt-1">
                        <option value="">– wählen –</option>
                        @foreach ($accounts as $acc)<option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->type === 'credit_card' ? 'Karte' : 'Giro' }})</option>@endforeach
                    </select>
                    @error('account_id') <span class="text-xs neg">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Format</label>
                    <select wire:model="format" class="field mt-1">
                        <option value="">Automatisch erkennen</option>
                        @foreach ($formats as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium">PDF-Auszüge <span class="text-dim">(mehrere möglich)</span></label>
                <input type="file" wire:model="pdfs" multiple accept="application/pdf"
                       class="mt-1 block w-full text-sm text-dim file:mr-3 file:rounded-md file:border-0 file:bg-[var(--accent)] file:px-3 file:py-2 file:font-medium file:text-black">
                <div wire:loading wire:target="pdfs" class="mt-1 text-xs text-dim">PDFs werden hochgeladen …</div>
                @error('pdfs.*') <span class="text-xs neg">{{ $message }}</span> @enderror
            </div>
            <div class="text-center text-xs uppercase tracking-widest text-dim">– oder –</div>
            <div>
                <label class="block text-sm font-medium">Text einfügen (Copy-Paste)</label>
                <textarea wire:model="pasteText" rows="4" class="field mt-1 font-mono text-xs"></textarea>
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="analyze,pdfs" class="btn btn-accent">
                <span wire:loading.remove wire:target="analyze">Auszüge parsen</span>
                <span wire:loading wire:target="analyze">Parse …</span>
            </button>
        </form>
    @endunless

    @if ($analyzed)
        {{-- Pro-Datei-Übersicht --}}
        <div class="glass p-5">
            <h2 class="mb-3 text-sm font-semibold">Erkannt</h2>
            <div class="space-y-2">
                @foreach ($fileMetas as $m)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b divider pb-2 last:border-0 text-sm">
                        <div>
                            <span class="font-medium">{{ $m['name'] ?? 'Eingefügter Text' }}</span>
                            <span class="text-xs text-dim">· {{ $m['label'] }}{{ $m['period_start'] ? ' · '.\Carbon\Carbon::parse($m['period_start'])->format('d.m.Y').'–'.\Carbon\Carbon::parse($m['period_end'])->format('d.m.Y') : '' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-dim">{{ $m['count'] }} Buchungen{{ $m['dups'] ? ' · '.$m['dups'].' doppelt' : '' }}</span>
                            @if ($m['balance_ok'])<span class="chip" style="color:var(--pos);border-color:var(--pos)">✓ Prüfsumme</span>
                            @else<span class="chip" style="color:var(--neg);border-color:var(--neg)">⚠ Prüfsumme</span>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Zusammenfassung --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="glass p-5"><div class="text-[11px] uppercase tracking-widest text-dim">Buchungen gesamt</div><div class="mt-1 text-2xl font-semibold kpi">{{ $totalCount }}</div></div>
            <div class="glass p-5"><div class="text-[11px] uppercase tracking-widest text-dim">Neu übernehmbar</div><div class="mt-1 text-2xl font-semibold kpi text-accent">{{ $newCount }}</div></div>
            <div class="glass p-5"><div class="text-[11px] uppercase tracking-widest text-dim">Duplikate (übersprungen)</div><div class="mt-1 text-2xl font-semibold kpi text-dim">{{ $dupCount }}</div></div>
        </div>

        {{-- Kleine Stichprobe (read-only) --}}
        @if (! empty($sample))
            <div class="glass overflow-x-auto">
                <div class="px-4 pt-4 text-xs text-dim">Auszug (erste {{ count($sample) }} Buchungen) – Kategorien korrigierst du nach dem Übernehmen im Korrektur-Tab.</div>
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b divider text-left text-xs uppercase tracking-wide text-dim">
                        <th class="px-3 py-2">Datum</th><th class="px-3 py-2">Empfänger / Zweck</th><th class="px-3 py-2 text-right">Betrag</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($sample as $row)
                            <tr class="border-b divider {{ ! empty($row['is_duplicate']) ? 'opacity-50' : '' }}">
                                <td class="whitespace-nowrap px-3 py-2 align-top text-dim">{{ \Carbon\Carbon::parse($row['booking_date'])->format('d.m.Y') }}</td>
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">{{ $row['counterparty'] ?: '—' }}</div>
                                    @if (! empty($row['is_duplicate']))<span class="chip">bereits vorhanden</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right align-top font-medium kpi {{ $row['amount_cents'] < 0 ? 'neg' : 'pos' }}">{{ money_format_de($row['amount_cents']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <button wire:click="cancel" class="btn btn-ghost">Abbrechen</button>
            {{-- Bewusst ein echtes Formular (kein wire:click): klassischer POST,
                 funktioniert garantiert auch bei großen Importen. --}}
            <form method="POST" action="{{ route('import.commit') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="account_id" value="{{ $account_id }}">
                <input type="hidden" name="format" value="{{ $fileMetas[0]['format'] ?? 'pdf' }}">
                <button type="submit" class="btn btn-accent">{{ $newCount }} Buchungen übernehmen</button>
            </form>
        </div>
    @endif
</div>
