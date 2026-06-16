<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ausgaben-Analyse: wohin ist das Geld geflossen — nach Kategorie,
 * getrennt nach Konto, über einen wählbaren Zeitraum. Reine Auswertung
 * (interne Transfers ausgeschlossen über den realExpenses-Scope).
 */
#[Title('Ausgaben')]
class Spending extends Component
{
    /** current | 3m | 6m | 12m | all */
    public string $period = '6m';

    public function setPeriod(string $p): void
    {
        $this->period = $p;
    }

    private function startDate(): ?Carbon
    {
        // Anker = letzter Monat mit Buchungen (nicht stur „jetzt"), damit auch
        // ältere Importe in „letzte 6 Monate" sichtbar bleiben.
        $latest = Transaction::max('booking_date');
        $anchor = $latest ? Carbon::parse($latest)->startOfMonth() : Carbon::now()->startOfMonth();

        return match ($this->period) {
            'current' => $anchor->copy(),
            '3m' => $anchor->copy()->subMonths(2),
            '6m' => $anchor->copy()->subMonths(5),
            '12m' => $anchor->copy()->subMonths(11),
            default => null, // all
        };
    }

    public function periodLabel(): string
    {
        return match ($this->period) {
            'current' => 'Aktueller Monat',
            '3m' => 'Letzte 3 Monate',
            '6m' => 'Letzte 6 Monate',
            '12m' => 'Letzte 12 Monate',
            default => 'Gesamter Zeitraum',
        };
    }

    public function render()
    {
        $start = $this->startDate();

        $base = Transaction::query()->realExpenses();
        if ($start !== null) {
            $base->where('booking_date', '>=', $start->toDateString());
        }

        // Summe je Konto + Kategorie (Beträge sind negativ -> Betrag der Ausgaben).
        $rows = (clone $base)
            ->select('account_id', 'category_id', DB::raw('SUM(amount_cents) as sum_cents'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('account_id', 'category_id')
            ->with(['account', 'category'])
            ->get();

        // Pro Konto gruppieren, Kategorien nach Betrag sortiert.
        $accounts = Account::orderBy('name')->get();
        $byAccount = [];
        foreach ($accounts as $acc) {
            $accRows = $rows->where('account_id', $acc->id)
                ->sortBy('sum_cents') // negativste zuerst
                ->map(fn ($r) => [
                    'category' => $r->category->name ?? 'Unkategorisiert',
                    'color' => $r->category->color ?? '#94a3b8',
                    'amount_cents' => (int) $r->sum_cents,
                    'cnt' => (int) $r->cnt,
                ])->values();
            $total = (int) $accRows->sum('amount_cents');
            if ($accRows->isNotEmpty()) {
                $byAccount[] = [
                    'name' => $acc->name,
                    'type' => $acc->type,
                    'total_cents' => $total,
                    'categories' => $accRows,
                ];
            }
        }

        // Gesamt über alle Konten je Kategorie (für die Übersicht oben).
        $overall = $rows->groupBy('category_id')->map(function ($grp) {
            $first = $grp->first();
            return [
                'category' => $first->category->name ?? 'Unkategorisiert',
                'color' => $first->category->color ?? '#94a3b8',
                'amount_cents' => (int) $grp->sum('sum_cents'),
            ];
        })->sortBy('amount_cents')->values();

        $grandTotal = (int) $rows->sum('sum_cents');

        return view('livewire.spending', [
            'byAccount' => $byAccount,
            'overall' => $overall,
            'grandTotalCents' => $grandTotal,
        ]);
    }
}
