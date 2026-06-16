<?php

namespace App\Imports\Parsers;

use App\Imports\Contracts\StatementParser;
use App\Imports\ParseResult;
use App\Imports\TransactionData;

/**
 * Parser für Sparkasse-Girokonto-Auszüge (Kontoauszug PDF), Quelle:
 * `pdftotext -layout`. Validiert gegen einen echten Auszug (39 Buchungen,
 * Summe == Schluss-Kontostand - Eröffnungs-Kontostand).
 *
 * Aufbau:
 *  Datum  Erläuterung [/ Wert: TT.MM.JJJJ]                       Betrag
 *         <Verwendungszweck, 1-3 eingerückte Folgezeilen>
 * Eröffnungs-/Schlusssaldo stehen als "Kontostand am ..." (ohne Datumsspalte)
 * und dienen als Prüfsumme.
 */
class SparkasseGiroPdfParser implements StatementParser
{
    // Zeile beginnt mit Datum, endet mit deutschem Betrag; dazwischen die Erläuterung.
    private const MAIN = '/^\s*(\d{2}\.\d{2}\.\d{4})\s+(.+?)\s+(-?[\d.]+,\d{2})\s*$/u';

    private const KSTAND = '/Kontostand am (\d{2}\.\d{2}\.\d{4}).*?(-?[\d.]+,\d{2})\s*$/u';

    // Kopf-/Fußzeilen, die jede Seite wiederholt -> schließen die Beschreibungssammlung.
    private const META = '/(Kontoauszug |GiroKomfort|^\s*Datum\s+Erläuterung|Sparkasse Koblenz|Bahnhofstr|^\s*56068|Anstalt des|Sparkassen-Finanzgruppe|^\s*S Sparkasse|Seite \d+ von|Vorstand|www\.sparkasse|HR Nr|^\s*l |Kontostand am|Übertrag)/u';

    public function format(): string
    {
        return 'sparkasse_giro_pdf';
    }

    public function label(): string
    {
        return 'Sparkasse – Girokonto-Auszug (PDF)';
    }

    public function detect(string $text): bool
    {
        return (str_contains($text, 'Sparkasse') && str_contains($text, 'Kontoauszug'))
            || str_contains($text, 'GiroKomfort');
    }

    public function parse(string $text): ParseResult
    {
        $lines = preg_split('/\R/', $text) ?: [];

        $rows = [];
        $openDesc = false;
        $opening = null;
        $closing = null;
        $firstDate = null;
        $lastDate = null;

        foreach ($lines as $line) {
            // Eröffnungs-/Schlusssaldo (erste Fundstelle = Eröffnung, letzte = Schluss).
            if (preg_match(self::KSTAND, $line, $m)) {
                $val = euros_to_cents($m[2]);
                if ($opening === null) {
                    $opening = $val;
                } else {
                    $closing = $val;
                }
                $openDesc = false;

                continue;
            }

            if (preg_match(self::MAIN, $line, $m)) {
                $erl = trim($m[2]);
                $value = null;
                if (preg_match('/Wert:\s*(\d{2}\.\d{2}\.\d{4})/u', $erl, $w)) {
                    $value = $w[1];
                    $erl = trim(preg_replace('#/?\s*Wert:.*$#u', '', $erl));
                }
                $rows[] = [
                    'booking' => $m[1],
                    'value' => $value,
                    'erl' => $erl,
                    'amount' => euros_to_cents($m[3]),
                    'desc' => [],
                    'raw' => [trim(preg_replace('/\s{2,}/', ' ', $line))],
                ];
                $firstDate ??= $m[1];
                $lastDate = $m[1];
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
                if (count($rows[$i]['desc']) < 3) {
                    $rows[$i]['desc'][] = $clean;
                }
            }
        }

        $txns = [];
        foreach ($rows as $r) {
            $verwendung = implode(' ', $r['desc']);
            $description = trim($r['erl'].($verwendung !== '' ? ' | '.$verwendung : ''));

            $txns[] = new TransactionData(
                bookingDate: de_date_to_iso($r['booking']),
                valueDate: de_date_to_iso($r['value']),
                amountCents: $r['amount'],
                counterparty: $this->guessCounterparty($verwendung, $r['erl']),
                description: $description,
                rawText: implode("\n", $r['raw']),
                paymentMethod: $this->guessPaymentMethod($r['erl'], $description),
                type: $r['amount'] < 0 ? 'expense' : 'income',
            );
        }

        return new ParseResult(
            $txns,
            $opening,
            $closing,
            de_date_to_iso($firstDate),
            de_date_to_iso($lastDate),
        );
    }

    /** Erstes aussagekräftiges Stück des Verwendungszwecks als Gegenpartei. */
    private function guessCounterparty(string $verwendung, string $erl): string
    {
        $src = $verwendung !== '' ? $verwendung : $erl;
        // Erste „Wort-Gruppe“ bis zu einer langen Ziffernkette / Trennzeichen.
        $first = preg_split('/\s{2,}|\s\d{6,}|\s\/\s|,\s/u', trim($src))[0] ?? $src;

        return mb_substr(trim($first), 0, 80);
    }

    private function guessPaymentMethod(string $erl, string $description): ?string
    {
        $e = mb_strtolower($erl);

        return match (true) {
            str_contains($e, 'lastschrift') => str_contains(strtoupper($description), 'PAYPAL') ? 'paypal' : 'direct_debit',
            str_contains($e, 'apple pay'), str_contains($e, 'debitkarten'), str_contains($e, 'kartenzahlung') => 'card',
            str_contains($e, 'bargeld') => 'cash',
            str_contains($e, 'dauerauftrag') => 'standing_order',
            str_contains($e, 'überweisung'), str_contains($e, 'gutschrift'), str_contains($e, 'rückbuchung') => 'transfer',
            default => null,
        };
    }
}
