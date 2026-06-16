<?php

namespace Database\Seeders;

use App\Models\Debt;
use App\Services\DebtPlanner;
use Illuminate\Database\Seeder;

/**
 * Seedet die zwei konkreten, bekannten Schulden (idempotent).
 * Beträge als Cent-Integer.
 */
class DebtSeeder extends Seeder
{
    public function run(): void
    {
        $planner = new DebtPlanner();

        // PayPal-Ratenkäufe (eBay). Zwei separate Pläne mit eigenen Raten/Terminen.
        // Werte laut Nutzerangabe; bei je 1 bereits bezahlter Rate startet der Plan
        // einen Monat vor der "nächsten Zahlung".
        $paypalPlans = [
            // [Name, Rate, Anzahl, Zahltag, Start (erste Rate)]
            ['PayPal eBay – Kauf 1', '114,42', 6, 21, '2026-05-21'],
            ['PayPal eBay – Kauf 2', '96,09', 6, 27, '2026-05-27'],
        ];
        foreach ($paypalPlans as [$name, $rate, $count, $day, $start]) {
            $debt = Debt::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'installment',
                    'total_amount_cents' => euros_to_cents($rate) * $count,
                    'installment_count' => $count,
                    'monthly_amount_cents' => euros_to_cents($rate),
                    'payment_day' => $day,
                    'start_date' => $start,
                    'status' => 'active',
                ],
            );
            if ($debt->payments()->count() === 0) {
                $planner->generate($debt);
                $planner->markPastDuePaid($debt->fresh());
            }
        }

        // 2) Kreditkarte Hanseatic: flexible Tilgung (revolving), ~1.260 €, Abbuchung 25., Start 01.06.2026.
        Debt::firstOrCreate(
            ['name' => 'Kreditkarte Hanseatic'],
            [
                'type' => 'revolving',
                'total_amount_cents' => euros_to_cents('1.260,00'),
                'payment_day' => 25,
                'start_date' => '2026-06-01',
                'status' => 'active',
            ],
        );
    }
}
