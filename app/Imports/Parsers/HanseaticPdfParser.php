<?php

namespace App\Imports\Parsers;

use App\Imports\Contracts\StatementParser;
use App\Imports\ParseResult;
use App\Imports\TransactionData;

/**
 * Parser für Hanseatic-Bank-Kreditkartenauszüge (GenialCard/Visa), Quelle:
 * `pdftotext -layout`. Validiert gegen einen echten Auszug (27 Buchungen,
 * Summe == Neuer - Alter Saldo).
 *
 * Zeilen-Varianten, die abgedeckt sind:
 *  - Kartenumsatz:  Buchungsdatum  Transaktionsdatum  "Kartenumsatz"  Karte  -Betrag
 *                   + 1-2 Folgezeilen mit Händler/Ort
 *  - Gutschrift:    Datum  -  "Gutschrift"  -  +Betrag   (Zahlungseingang/Erstattung)
 *  - Zinsbelastung: Datum  -  "Zinsbelastung vom ..."  -  -Betrag (inline, keine Folgezeile)
 * Ausgeschlossen (Meta): Alter/Neuer Saldo, Seitenüberträge, Visa-Zwischensumme, Köpfe.
 */
class HanseaticPdfParser implements StatementParser
{
    // Datum1, (Datum2|-), Beschreibung, (Karte 4-stellig|-), Betrag am Zeilenende.
    private const MAIN = '/^\s*(\d{2}\.\d{2}\.\d{4})\s+(\d{2}\.\d{2}\.\d{4}|-)\s+(.+?)\s+(\d{4}|-)\s+(-?[\d.]+,\d{2})\s*$/u';

    private const META = '/(Übertrag Saldo|Alter Saldo|Neuer Saldo|Buchungs-|^\s*datum|Visa \d{4}|Verfügungsrahmen)/u';

    public function format(): string
    {
        return 'hanseatic_pdf';
    }

    public function label(): string
    {
        return 'Hanseatic Bank – Kreditkartenauszug (PDF)';
    }

    public function detect(string $text): bool
    {
        return str_contains($text, 'Hanseatic Bank')
            || str_contains($text, 'GenialCard')
            || str_contains($text, 'Kartenkonto');
    }

    public function parse(string $text): ParseResult
    {
        $lines = preg_split('/\R/', $text) ?: [];

        /** @var TransactionData[] $txns */
        $txns = [];
        $openDesc = false;          // sammeln wir gerade Folgezeilen für die letzte Buchung?
        $opening = null;
        $closing = null;
        $periodStart = null;
        $periodEnd = null;

        // Zwischenspeicher der rohen Treffer, damit Folgezeilen angehängt werden können.
        $rows = [];

        foreach ($lines as $line) {
            if (preg_match('/Abrechnungszeitraum:\s*(\d{2}\.\d{2}\.\d{4})\s*-\s*(\d{2}\.\d{2}\.\d{4})/u', $line, $m)) {
                $periodStart = de_date_to_iso($m[1]);
                $periodEnd = de_date_to_iso($m[2]);
            }
            if (preg_match('/Alter Saldo\s+(-?[\d.]+,\d{2})/u', $line, $m)) {
                $opening = euros_to_cents($m[1]);
            }
            if (preg_match('/Neuer Saldo\s+(-?[\d.]+,\d{2})/u', $line, $m)) {
                $closing = euros_to_cents($m[1]);
            }

            if (preg_match(self::MAIN, $line, $m)) {
                $rows[] = [
                    'booking' => $m[1],
                    'value' => $m[2] === '-' ? null : $m[2],
                    'label' => trim($m[3]),
                    'card' => $m[4] === '-' ? null : $m[4],
                    'amount' => euros_to_cents($m[5]),
                    'desc' => [],
                    'raw' => [trim(preg_replace('/\s{2,}/', ' ', $line))],
                ];
                $openDesc = true;

                continue;
            }

            if (preg_match(self::META, $line)) {
                $openDesc = false;

                continue;
            }

            if ($openDesc && trim($line) !== '') {
                $i = count($rows) - 1;
                $clean = trim(preg_replace('/\s{2,}/', ' ', $line));
                $rows[$i]['raw'][] = $clean;
                if (count($rows[$i]['desc']) < 2) {
                    $rows[$i]['desc'][] = $clean;
                }
            }
        }

        foreach ($rows as $r) {
            $merchant = implode(' ', $r['desc']);                 // Händlerzeilen
            $counterparty = $merchant !== '' ? $merchant : $r['label'];
            $description = trim($r['label'].($merchant !== '' ? ' | '.$merchant : ''));

            $txns[] = new TransactionData(
                bookingDate: de_date_to_iso($r['booking']),
                valueDate: de_date_to_iso($r['value']),
                amountCents: $r['amount'],
                counterparty: $this->shorten($counterparty),
                description: $description,
                rawText: implode("\n", $r['raw']),
                paymentMethod: $this->guessPaymentMethod($description),
                type: $r['amount'] < 0 ? 'expense' : 'income',
            );
        }

        return new ParseResult($txns, $opening, $closing, $periodStart, $periodEnd);
    }

    private function guessPaymentMethod(string $text): string
    {
        return str_contains(strtoupper($text), 'PAYPAL') ? 'paypal' : 'card';
    }

    private function shorten(string $s, int $max = 120): string
    {
        $s = trim($s);

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }
}
