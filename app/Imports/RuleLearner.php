<?php

namespace App\Imports;

use App\Models\CategoryRule;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Lernen aus Korrekturen (Phase 3): legt aus einer manuellen Umkategorisierung
 * eine wiederverwendbare Regel an und wendet sie laut Nutzerwunsch SOFORT
 * RÜCKWIRKEND auf alle passenden Bestandsbuchungen an.
 */
class RuleLearner
{
    /**
     * @return array{rule: CategoryRule, affected: int}
     */
    public function learn(
        string $field,
        string $pattern,
        int $categoryId,
        ?string $accountType = null,
        string $matchType = 'contains',
    ): array {
        $pattern = trim($pattern);

        return DB::transaction(function () use ($field, $pattern, $categoryId, $accountType, $matchType) {
            $rule = CategoryRule::firstOrCreate(
                [
                    'field' => $field,
                    'match_type' => $matchType,
                    'pattern' => $pattern,
                    'account_type' => $accountType,
                ],
                [
                    'category_id' => $categoryId,
                    'priority' => 100,        // gelernte Regeln schlagen Seed-Regeln (Standard 50/10).
                    'auto_created' => true,
                ],
            );

            // Existierte schon -> Ziel-Kategorie aktualisieren.
            if (! $rule->wasRecentlyCreated && $rule->category_id !== $categoryId) {
                $rule->update(['category_id' => $categoryId]);
            }

            $affected = $this->applyRetroactively($rule);

            return ['rule' => $rule, 'affected' => $affected];
        });
    }

    /**
     * Wendet eine Regel auf alle bestehenden Buchungen an, die passen und noch
     * nicht die Ziel-Kategorie tragen. Gibt die Anzahl geänderter Buchungen zurück.
     */
    public function applyRetroactively(CategoryRule $rule): int
    {
        $field = $rule->field;

        // Kontotyp-Filter (Regel ggf. nur Giro oder nur Karte).
        $base = Transaction::query()->with('account');
        if ($rule->account_type !== null) {
            $base->whereHas('account', fn ($q) => $q->where('type', $rule->account_type));
        }

        $affected = 0;
        $base->orderBy('id')->chunkById(500, function ($txns) use ($rule, $field, &$affected) {
            foreach ($txns as $t) {
                if ((int) $t->category_id === (int) $rule->category_id) {
                    continue;
                }
                if ($rule->matchesValue($this->fieldValue($t, $field))) {
                    $t->update(['category_id' => $rule->category_id]);
                    $affected++;
                }
            }
        });

        return $affected;
    }

    private function fieldValue(Transaction $t, string $field): ?string
    {
        return match ($field) {
            'counterparty' => $t->counterparty,
            'description' => $t->description,
            'raw_text' => $t->raw_text,
            default => null,
        };
    }

    /**
     * Schlägt aus einem Empfänger-Text ein sinnvolles, kurzes Muster vor
     * (erstes aussagekräftiges Wort), z.B. "ARAL Koblenz ..." -> "ARAL".
     */
    public static function suggestPattern(?string $counterparty): string
    {
        $s = trim((string) $counterparty);
        if ($s === '') {
            return '';
        }
        // Erstes "Wort" >= 3 Zeichen, Sonderzeichen am Rand weg.
        foreach (preg_split('/\s+/', $s) as $word) {
            $w = trim($word, " \t.,/-*");
            if (mb_strlen($w) >= 3) {
                return $w;
            }
        }

        return mb_substr($s, 0, 20);
    }
}
