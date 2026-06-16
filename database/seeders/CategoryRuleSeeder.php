<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryRule;
use Illuminate\Database\Seeder;

/**
 * Start-Regelsatz (idempotent). Entspricht den bisher im SimpleCategorizer
 * fest verdrahteten Fällen, jetzt als editierbare DB-Regeln. priority 50 =
 * Standard; gelernte Regeln (priority 100) gewinnen darüber.
 */
class CategoryRuleSeeder extends Seeder
{
    /** [Kategoriename, [pattern...]] — alle als 'contains' auf raw_text. */
    private const SEED = [
        ['Sprit',          ['SB-TANK', 'ARAL', 'SHELL', 'ESSO', 'JET', 'ED-TANKSTELLE', 'TANKSTELLE']],
        ['PayPal',         ['PAYPAL', 'PP.7011']],
        ['Essen',          ['REWE', 'PENNY', 'EDEKA', 'ALDI', 'LIDL', 'KAUFLAND', 'GLOBUS', 'MCDONALD', 'TAKEAWAY', 'LIEFERANDO']],
        ['Versicherung',   ['DEVK', 'ALLIANZ', 'HUK', 'DEBEKA', 'VERSICHERUNG']],
        ['Kredit / Raten', ['SANTANDER', 'ZINSBELASTUNG', 'RATENKREDIT', 'CONSUMER BANK']],
        ['Gym',            ['MCFIT', 'FITNESS', 'CLEVER FIT', 'STAHLWERK']],
        ['Einkommen',      ['GEHALT', 'LOHN', 'EROCK']],
        ['Wohnen',         ['MIETE', 'STADTWERKE', 'STW KOBLENZ']],
    ];

    public function run(): void
    {
        foreach (self::SEED as [$catName, $patterns]) {
            $categoryId = Category::where('name', $catName)->value('id');
            if ($categoryId === null) {
                continue;
            }
            foreach ($patterns as $pattern) {
                CategoryRule::firstOrCreate(
                    ['field' => 'raw_text', 'match_type' => 'contains', 'pattern' => $pattern, 'account_type' => null],
                    ['category_id' => $categoryId, 'priority' => 50, 'auto_created' => false],
                );
            }
        }
    }
}
