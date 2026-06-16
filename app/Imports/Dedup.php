<?php

namespace App\Imports;

use App\Models\Transaction;

/**
 * Verhindert Doppelimporte. Der Hash identifiziert eine Buchung fachlich
 * eindeutig genug, ohne von Formatierungs-Rauschen abzuhängen.
 *
 * dedup_hash = sha1(account_id | booking_date | amount_cents | normalisierter Text)
 * normalize  = lowercase, Sonderzeichen weg, Mehrfach-Leerzeichen weg.
 */
class Dedup
{
    public static function hash(int $accountId, TransactionData $t): string
    {
        $text = self::normalize(($t->counterparty ?? '').' '.($t->description ?? ''));

        return sha1(implode('|', [$accountId, $t->bookingDate, $t->amountCents, $text]));
    }

    public static function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^a-z0-9äöüß ]+/u', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }

    /**
     * Welche der übergebenen Hashes existieren für dieses Konto bereits?
     *
     * @param  string[]  $hashes
     * @return array<string,bool>  hash => true
     */
    public static function existingHashes(int $accountId, array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        return Transaction::query()
            ->where('account_id', $accountId)
            ->whereIn('dedup_hash', array_values(array_unique($hashes)))
            ->pluck('dedup_hash')
            ->flip()
            ->map(fn () => true)
            ->all();
    }
}
