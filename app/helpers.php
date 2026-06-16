<?php

if (! function_exists('money_format_de')) {
    /**
     * Cent-Integer -> deutsche Geldanzeige, z.B. -123456 => "-1.234,56 €".
     */
    function money_format_de(?int $cents, bool $withSymbol = true): string
    {
        $cents = $cents ?? 0;
        $value = number_format($cents / 100, 2, ',', '.');

        return $withSymbol ? $value.' €' : $value;
    }
}

if (! function_exists('de_date_to_iso')) {
    /**
     * Deutsches Datum "TT.MM.JJJJ" -> ISO "JJJJ-MM-TT".
     * Gibt null zurück bei leerem/ungültigem Wert (z.B. "-" im Auszug).
     */
    function de_date_to_iso(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if (! preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
            return null;
        }

        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
}

if (! function_exists('euros_to_cents')) {
    /**
     * Eingabe (z.B. "1.234,56" oder "1234.56" oder 12.34) -> Cent-Integer.
     * Niemals Float speichern.
     */
    function euros_to_cents(string|float|int|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = trim($value);
            // Deutsches Format: Tausenderpunkt entfernen, Komma -> Punkt.
            if (str_contains($value, ',')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
            $value = (float) $value;
        }

        return (int) round($value * 100);
    }
}
