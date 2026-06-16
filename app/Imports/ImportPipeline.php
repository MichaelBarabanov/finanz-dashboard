<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Orchestriert den Import: Text -> Parser -> Dedup -> Categorizer -> Vorschau-Zeilen.
 * Committet NIEMALS automatisch — `commit()` wird erst nach Bestätigung in der UI aufgerufen.
 */
class ImportPipeline
{
    public function __construct(
        private ParserRegistry $registry = new ParserRegistry(),
    ) {}

    /**
     * Baut die Vorschau. Persistiert nichts.
     *
     * @return array{
     *   format: string,
     *   label: string,
     *   balance_ok: bool,
     *   opening_cents: int|null,
     *   closing_cents: int|null,
     *   sum_cents: int,
     *   expected_delta_cents: int|null,
     *   period_start: string|null,
     *   period_end: string|null,
     *   rows: array<int,array<string,mixed>>,
     *   duplicate_count: int,
     * }
     */
    public function analyze(int $accountId, string $text, ?string $format = null): array
    {
        $parser = $format !== null
            ? $this->registry->byFormat($format)
            : ($this->registry->detect($text) ?? throw new \RuntimeException(
                'Format nicht erkannt. Bitte manuell wählen.'
            ));

        $result = $parser->parse($text);
        $categorizer = new RuleEngine();
        $accountType = Account::whereKey($accountId)->value('type');

        // Dedup vorbereiten: alle Hashes auf einmal gegen die DB prüfen (1 Query).
        $hashes = [];
        foreach ($result->transactions as $t) {
            $hashes[] = Dedup::hash($accountId, $t);
        }
        $existing = Dedup::existingHashes($accountId, $hashes);

        // Auch Duplikate INNERHALB des Stapels markieren (z.B. zweimal gleicher Betrag/Tag/Text).
        $seen = [];
        $rows = [];
        $dupCount = 0;
        foreach ($result->transactions as $i => $t) {
            $hash = $hashes[$i];
            $isDup = isset($existing[$hash]) || isset($seen[$hash]);
            $seen[$hash] = true;
            if ($isDup) {
                $dupCount++;
            }

            $class = $categorizer->classify($t, $accountType);

            $rows[] = [
                'booking_date' => $t->bookingDate,
                'value_date' => $t->valueDate,
                'amount_cents' => $t->amountCents,
                'counterparty' => $t->counterparty,
                'description' => $t->description,
                'raw_text' => $t->rawText,
                'payment_method' => $t->paymentMethod,
                'type' => $class['type'],
                'is_internal_transfer' => $class['is_internal_transfer'],
                'category_id' => $class['category_id'],
                'dedup_hash' => $hash,
                'is_duplicate' => $isDup,
                'selected' => ! $isDup,   // Duplikate sind standardmäßig abgewählt.
            ];
        }

        return [
            'format' => $parser->format(),
            'label' => $parser->label(),
            'balance_ok' => $result->balanceCheckPasses(),
            'opening_cents' => $result->openingCents,
            'closing_cents' => $result->closingCents,
            'sum_cents' => $result->sumCents(),
            'expected_delta_cents' => $result->expectedDeltaCents(),
            'period_start' => $result->periodStart,
            'period_end' => $result->periodEnd,
            'rows' => $rows,
            'duplicate_count' => $dupCount,
        ];
    }

    /**
     * Persistiert die ausgewählten Zeilen in einer Transaktion. Gibt das ImportBatch zurück.
     *
     * @param  array<int,array<string,mixed>>  $rows  (i.d.R. die — ggf. editierten — Vorschau-Zeilen)
     */
    public function commit(int $accountId, string $format, ?string $fileName, array $rows): ImportBatch
    {
        return DB::transaction(function () use ($accountId, $format, $fileName, $rows) {
            $batch = ImportBatch::create([
                'account_id' => $accountId,
                'source_format' => $format,
                'file_name' => $fileName,
                'imported_at' => now(),
                'row_count' => 0,
                'status' => 'committed',
            ]);

            $count = 0;
            foreach ($rows as $row) {
                // Duplikate immer überspringen; sonst übernehmen, AUSSER die Zeile
                // wurde explizit abgewählt (selected === false). Das ist robust
                // gegen Hydrierungs-Eigenheiten der Checkbox-Werte (null/"0").
                $explicitlyDeselected = array_key_exists('selected', $row) && $row['selected'] === false;
                if (! empty($row['is_duplicate']) || $explicitlyDeselected) {
                    continue;
                }

                Transaction::create([
                    'account_id' => $accountId,
                    'booking_date' => $row['booking_date'],
                    'value_date' => $row['value_date'] ?? null,
                    'amount_cents' => (int) $row['amount_cents'],
                    'currency' => 'EUR',
                    'counterparty' => $row['counterparty'] ?? null,
                    'description' => $row['description'] ?? null,
                    'raw_text' => $row['raw_text'] ?? null,
                    'category_id' => $row['category_id'] ?? null,
                    'payment_method' => $row['payment_method'] ?? null,
                    'type' => $row['type'] ?? 'expense',
                    'is_internal_transfer' => (bool) ($row['is_internal_transfer'] ?? false),
                    'is_manual' => false,
                    'dedup_hash' => $row['dedup_hash'],
                    'import_batch_id' => $batch->id,
                ]);
                $count++;
            }

            $batch->update(['row_count' => $count]);

            return $batch;
        });
    }
}
