<?php

namespace App\Imports;

/**
 * Ergebnis eines Parse-Laufs: die Buchungen plus Auszugs-Metadaten.
 *
 * Die Salden (opening/closing) sind bewusst Teil des Ergebnisses: damit kann die
 * Pipeline eine PRÜFSUMME bilden (Summe aller Buchungen muss closing - opening
 * ergeben). Schlägt sie fehl, wurde der Auszug falsch geparst -> hart in der
 * Vorschau warnen, nichts blind übernehmen.
 */
final class ParseResult
{
    /**
     * @param  TransactionData[]  $transactions
     */
    public function __construct(
        public array $transactions = [],
        public ?int $openingCents = null,
        public ?int $closingCents = null,
        public ?string $periodStart = null,   // Y-m-d
        public ?string $periodEnd = null,      // Y-m-d
    ) {}

    public function sumCents(): int
    {
        return array_sum(array_map(fn (TransactionData $t) => $t->amountCents, $this->transactions));
    }

    /** Erwartete Differenz aus den Salden, falls beide bekannt. */
    public function expectedDeltaCents(): ?int
    {
        if ($this->openingCents === null || $this->closingCents === null) {
            return null;
        }

        return $this->closingCents - $this->openingCents;
    }

    /** true, wenn keine Salden-Prüfung möglich ODER Prüfsumme stimmt. */
    public function balanceCheckPasses(): bool
    {
        $delta = $this->expectedDeltaCents();

        return $delta === null || $delta === $this->sumCents();
    }
}
