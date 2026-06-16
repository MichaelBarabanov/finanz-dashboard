<?php

namespace App\Imports;

use App\Models\Category;

/**
 * PLATZHALTER für Phase 3 (DB-gestützte Regel-Engine, die aus Korrekturen lernt).
 *
 * Bis dahin: ein kleiner, fest verdrahteter Schlüsselwort-Matcher, der die
 * offensichtlichen Fälle vorbelegt, damit die Import-Vorschau nicht komplett
 * „Sonstiges“ ist. Bewusst klein gehalten — die Wahrheit setzt der Nutzer in
 * der Vorschau, und Phase 3 ersetzt diese Klasse durch `category_rules`.
 *
 * Erkennt zusätzlich interne Transfers (Kreditkarten-Ausgleich Giro <-> Karte),
 * die aus den Auswertungen rausfallen müssen.
 */
class SimpleCategorizer
{
    /** @var array<string,int> name => id */
    private array $catIds;

    /**
     * Reihenfolge = Priorität (erster Treffer gewinnt).
     * [Kategoriename, [Schlüsselwörter...]]
     *
     * @var array<int,array{0:string,1:string[]}>
     */
    private const RULES = [
        ['Sprit',          ['SB-TANK', 'SB TANK', 'ARAL', 'SHELL', 'ESSO', 'JET', 'ED-TANKSTELLE', 'TANKSTELLE', 'TANK ']],
        ['PayPal',         ['PAYPAL', 'PP.7011']],
        ['Essen',          ['REWE', 'PENNY', 'EDEKA', 'ALDI', 'LIDL', 'KAUFLAND', 'GLOBUS', 'MCDONALD', 'BURGER', 'NETTO', 'BÄCK', 'TAKEAWAY', 'LIEFERANDO']],
        ['Versicherung',   ['DEVK', 'ALLIANZ', 'HUK', 'DEBEKA', 'VERSICHERUNG', 'VERSICHERUNGS']],
        ['Kredit / Raten', ['SANTANDER', 'ZINSBELASTUNG', 'KREDIT', 'RATENKREDIT', 'CONSUMER BANK']],
        ['Gym',            ['MCFIT', 'FITNESS', 'CLEVER FIT', 'STAHLWERK', 'GYM']],
        ['Einkommen',      ['GEHALT', 'LOHN', 'EROCK', 'EROCK MARKETING']],
        ['Wohnen',         ['MIETE', 'STADTWERKE', 'STW KOBLENZ', 'STROM', 'EVM']],
    ];

    public function __construct()
    {
        $this->catIds = Category::query()->pluck('id', 'name')->all();
    }

    /**
     * @return array{category_id: int|null, is_internal_transfer: bool, type: string}
     */
    public function classify(TransactionData $t): array
    {
        $haystack = mb_strtoupper(($t->counterparty ?? '').' '.($t->description ?? '').' '.$t->rawText);

        // 1) Interner Transfer: Kreditkarten-Ausgleich zwischen Giro und Karte.
        if (preg_match('/KARTENABRECHNUNG|HANSEATIC BANK/u', $haystack)) {
            return ['category_id' => null, 'is_internal_transfer' => true, 'type' => 'transfer'];
        }

        // 2) Schlüsselwort-Regeln.
        foreach (self::RULES as [$catName, $keywords]) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return [
                        'category_id' => $this->catIds[$catName] ?? null,
                        'is_internal_transfer' => false,
                        'type' => $t->type,
                    ];
                }
            }
        }

        // 3) Einnahme ohne Treffer -> Kategorie Einkommen, sonst offen lassen.
        if ($t->type === 'income') {
            return ['category_id' => $this->catIds['Einkommen'] ?? null, 'is_internal_transfer' => false, 'type' => 'income'];
        }

        return ['category_id' => null, 'is_internal_transfer' => false, 'type' => $t->type];
    }
}
