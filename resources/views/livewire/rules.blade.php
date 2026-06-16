<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight">Kategorisierungs-Regeln</h1>
        <p class="text-sm text-dim">Treffer mit höchster Priorität gewinnt</p>
    </div>

    @if (session('saved'))
        <div class="glass px-4 py-3 text-sm pos">{{ session('saved') }}</div>
    @endif

    <form wire:submit="save" class="glass grid gap-3 p-5 sm:grid-cols-6">
        <div class="sm:col-span-2">
            <label class="block text-xs text-dim">Muster</label>
            <input wire:model="pattern" placeholder="z.B. ARAL" class="field mt-1">
            @error('pattern') <span class="text-xs neg">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-xs text-dim">Feld</label>
            <select wire:model="field" class="field mt-1">
                <option value="raw_text">Rohtext</option>
                <option value="counterparty">Empfänger</option>
                <option value="description">Zweck</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-dim">Vergleich</label>
            <select wire:model="match_type" class="field mt-1">
                <option value="contains">enthält</option>
                <option value="starts_with">beginnt mit</option>
                <option value="exact">exakt</option>
                <option value="regex">Regex</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-dim">Kategorie</label>
            <select wire:model="category_id" class="field mt-1">
                <option value="">– wählen –</option>
                @foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
            </select>
            @error('category_id') <span class="text-xs neg">{{ $message }}</span> @enderror
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn btn-accent w-full">Regel anlegen</button>
        </div>
    </form>

    <div class="glass overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b divider text-left text-xs uppercase tracking-wide text-dim">
                    <th class="px-3 py-2">Prio</th>
                    <th class="px-3 py-2">Wenn</th>
                    <th class="px-3 py-2">Muster</th>
                    <th class="px-3 py-2">→ Kategorie</th>
                    <th class="px-3 py-2">Konto</th>
                    <th class="px-3 py-2">Quelle</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-b divider">
                        <td class="px-3 py-2 kpi">{{ $rule->priority }}</td>
                        <td class="px-3 py-2 text-dim">
                            {{ ['counterparty'=>'Empfänger','description'=>'Zweck','raw_text'=>'Rohtext'][$rule->field] ?? $rule->field }}
                            {{ ['contains'=>'enthält','starts_with'=>'beginnt mit','exact'=>'exakt','regex'=>'Regex'][$rule->match_type] ?? $rule->match_type }}
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $rule->pattern }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $rule->category->color ?? '#94a3b8' }}"></span>
                                {{ $rule->category->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-dim">{{ $rule->account_type ? ($rule->account_type === 'credit_card' ? 'Karte' : 'Giro') : 'alle' }}</td>
                        <td class="px-3 py-2">
                            @if ($rule->auto_created)
                                <span class="chip" style="color:var(--accent);border-color:var(--accent)">gelernt</span>
                            @else
                                <span class="chip">System</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <button wire:click="applyNow({{ $rule->id }})" class="text-xs text-dim hover:text-accent">anwenden</button>
                            <button wire:click="delete({{ $rule->id }})" wire:confirm="Regel löschen?" class="ml-2 text-xs neg hover:underline">löschen</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-dim">Noch keine Regeln.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
