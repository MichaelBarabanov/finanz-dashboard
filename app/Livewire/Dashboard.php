<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Übersicht')]
class Dashboard extends Component
{
    public function render()
    {
        $now = Carbon::now();

        // Bezugsmonat = letzter Monat MIT Buchungen (nicht stur der Kalendermonat).
        // So zeigt die Übersicht echte Werte, auch wenn man Vergangenes importiert
        // und der laufende Monat noch leer ist.
        $latest = Transaction::max('booking_date');
        $ref = $latest ? Carbon::parse($latest) : $now;
        $year = $ref->year;
        $month = $ref->month;

        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        $totalBalance = $accounts->sum(fn (Account $a) => $a->balance_cents);

        // Monat: echte Einnahmen / Ausgaben (interne Transfers ausgeschlossen).
        $incomeCents = (int) Transaction::query()->realIncome()->inMonth($year, $month)->sum('amount_cents');
        $expenseCents = (int) Transaction::query()->realExpenses()->inMonth($year, $month)->sum('amount_cents'); // negativ
        $netCents = $incomeCents + $expenseCents;

        // Top-Ausgabenkategorien diesen Monat.
        $topCategories = Transaction::query()
            ->realExpenses()
            ->inMonth($year, $month)
            ->whereNotNull('category_id')
            ->select('category_id', DB::raw('SUM(amount_cents) as sum_cents'))
            ->groupBy('category_id')
            ->with('category')
            ->orderBy('sum_cents') // negativste zuerst
            ->limit(6)
            ->get();

        $recent = Transaction::with(['account', 'category'])
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        // 6-Monats-Serie für das Diagramm.
        $labels = [];
        $incomeSeries = [];
        $expenseSeries = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $ref->copy()->startOfMonth()->subMonths($i);
            $labels[] = $d->locale('de')->isoFormat('MMM YY');
            $incomeSeries[] = round(((int) Transaction::query()->realIncome()->inMonth($d->year, $d->month)->sum('amount_cents')) / 100, 2);
            $expenseSeries[] = round(abs((int) Transaction::query()->realExpenses()->inMonth($d->year, $d->month)->sum('amount_cents')) / 100, 2);
        }

        // Kreditkarten-Verlauf: Saldo je Monatsende (letzte 6 Monate), aus
        // Transaktionen abgeleitet (Anfangssaldo + Summe bis Monatsende).
        $cardAccounts = $accounts->where('type', 'credit_card');
        $cardLabels = [];
        $cardSeries = [];
        if ($cardAccounts->isNotEmpty()) {
            $cardIds = $cardAccounts->pluck('id')->all();
            $cardOpening = (int) $cardAccounts->sum('opening_balance_cents');
            for ($i = 5; $i >= 0; $i--) {
                $end = $ref->copy()->startOfMonth()->subMonths($i)->endOfMonth();
                $sum = (int) Transaction::query()
                    ->whereIn('account_id', $cardIds)
                    ->where('booking_date', '<=', $end->toDateString())
                    ->sum('amount_cents');
                $cardLabels[] = $end->locale('de')->isoFormat('MMM YY');
                $cardSeries[] = round(($cardOpening + $sum) / 100, 2);
            }
        }

        // Schulden-Überblick + fällige Raten der nächsten 14 Tage.
        $activeDebts = Debt::active()->with('payments')->get();
        $totalDebtCents = (int) $activeDebts->sum(fn (Debt $d) => $d->remaining_cents);
        $upcomingPayments = DebtPayment::query()
            ->where('paid', false)
            ->whereBetween('due_date', [$now->copy()->startOfDay(), $now->copy()->addDays(14)->endOfDay()])
            ->whereHas('debt', fn ($q) => $q->where('status', 'active'))
            ->with('debt')
            ->orderBy('due_date')
            ->get();

        return view('livewire.dashboard', [
            'accounts' => $accounts,
            'totalBalance' => $totalBalance,
            'totalDebtCents' => $totalDebtCents,
            'upcomingPayments' => $upcomingPayments,
            'incomeCents' => $incomeCents,
            'expenseCents' => $expenseCents,
            'netCents' => $netCents,
            'topCategories' => $topCategories,
            'recent' => $recent,
            'chartLabels' => $labels,
            'chartIncome' => $incomeSeries,
            'chartExpense' => $expenseSeries,
            'cardLabels' => $cardLabels,
            'cardSeries' => $cardSeries,
            'monthLabel' => $ref->locale('de')->isoFormat('MMMM YYYY'),
        ]);
    }
}
