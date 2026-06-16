<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Erzeugt und pflegt Ratenpläne.
 *
 * installment: kompletter Plan beim Anlegen (start_date + payment_day, N Raten).
 * revolving:   kein fester Plan — debt_payments werden aus echten Tilgungen gefüllt.
 */
class DebtPlanner
{
    /**
     * (Neu) generiert den Ratenplan einer installment-Schuld.
     * Bestehende Plan-Einträge (unbezahlt, ohne Buchungsverknüpfung) werden ersetzt;
     * bereits bezahlte/gematchte Raten bleiben erhalten.
     */
    public function generate(Debt $debt): void
    {
        if (! $debt->isInstallment() || ! $debt->installment_count || ! $debt->monthly_amount_cents) {
            return;
        }

        DB::transaction(function () use ($debt) {
            // Nur ungenutzte Plan-Einträge entfernen (bezahlte/gematchte schützen).
            $debt->payments()->where('paid', false)->whereNull('transaction_id')->delete();

            $existing = $debt->payments()->pluck('due_date')->map(
                fn ($d) => Carbon::parse($d)->toDateString()
            )->all();

            $base = Carbon::parse($debt->start_date);
            $count = (int) $debt->installment_count;
            $rate = (int) $debt->monthly_amount_cents;
            $total = (int) $debt->total_amount_cents;

            for ($i = 0; $i < $count; $i++) {
                $due = $this->dueDate($base, $i, (int) $debt->payment_day);
                if (in_array($due->toDateString(), $existing, true)) {
                    continue; // schon vorhanden (z.B. bereits bezahlt)
                }

                // Letzte Rate trägt den Rundungsrest, damit Summe == Gesamtschuld.
                $amount = $i === $count - 1 ? ($total - $rate * ($count - 1)) : $rate;

                DebtPayment::create([
                    'debt_id' => $debt->id,
                    'due_date' => $due->toDateString(),
                    'amount_cents' => $amount,
                    'paid' => false,
                ]);
            }
        });
    }

    /**
     * Markiert alle Raten als bezahlt, deren Fälligkeit in der Vergangenheit liegt.
     * Nützlich beim Anlegen einer bereits laufenden Schuld (Seed/Erfassung).
     */
    public function markPastDuePaid(Debt $debt, ?Carbon $asOf = null): int
    {
        $asOf ??= Carbon::today();
        $n = 0;
        foreach ($debt->payments()->where('paid', false)->get() as $p) {
            if (Carbon::parse($p->due_date)->lte($asOf)) {
                $p->update(['paid' => true, 'paid_date' => $p->due_date]);
                $n++;
            }
        }

        return $n;
    }

    /** Fälligkeitsdatum der i-ten Rate: Startmonat + i Monate, auf payment_day geklemmt. */
    private function dueDate(Carbon $start, int $i, int $paymentDay): Carbon
    {
        $month = $start->copy()->startOfMonth()->addMonths($i);
        $day = min($paymentDay, $month->daysInMonth);

        return $month->day($day);
    }
}
