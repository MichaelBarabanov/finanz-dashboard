<?php

namespace App\Imports;

/**
 * Einheitliches, format-unabhängiges Transfer-Objekt zwischen Parser und Pipeline.
 *
 * Jeder Parser (egal ob Sparkasse-PDF, Hanseatic-PDF, später CSV/Text) liefert
 * ausschließlich solche DTOs. Dadurch kennen Dedup, Categorizer und Persist-Schicht
 * NUR diese eine Struktur und müssen nichts über das Quellformat wissen.
 *
 * Beträge sind IMMER Cent-Integer und signed (Ausgabe negativ, Einnahme positiv).
 * Datumswerte sind ISO-Strings (Y-m-d), zentral aus dem deutschen Format konvertiert.
 */
final class TransactionData
{
    public function __construct(
        public string $bookingDate,        // Y-m-d
        public ?string $valueDate,         // Y-m-d | null (Wertstellung)
        public int $amountCents,           // signed
        public ?string $counterparty,      // Händler/Empfänger (kurz)
        public ?string $description,       // Verwendungszweck (lang)
        public string $rawText,            // Originalzeile(n), für Nachvollziehbarkeit/Reparsing
        public ?string $paymentMethod = null, // card|transfer|direct_debit|paypal|cash|standing_order
        public string $type = 'expense',  // income|expense|transfer
        public bool $isInternalTransfer = false,
        public string $currency = 'EUR',
    ) {}

    /** Als Array für Transaction::create() (ohne abgeleitete/Pipeline-Felder). */
    public function toAttributes(): array
    {
        return [
            'booking_date' => $this->bookingDate,
            'value_date' => $this->valueDate,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'counterparty' => $this->counterparty,
            'description' => $this->description,
            'raw_text' => $this->rawText,
            'payment_method' => $this->paymentMethod,
            'type' => $this->type,
            'is_internal_transfer' => $this->isInternalTransfer,
            'is_manual' => false,
        ];
    }
}
