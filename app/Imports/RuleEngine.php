<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\CategoryRule;
use Illuminate\Support\Collection;

/**
 * DB-gestützte Kategorisierung (Phase 3) — ersetzt den SimpleCategorizer.
 *
 * Reihenfolge:
 *  1. Struktureller Check: interner Transfer (Kreditkarten-Ausgleich Giro <-> Karte).
 *     Das ist keine Kategorie, sondern ein Flag — bleibt fest verdrahtet.
 *  2. category_rules nach Priorität, erster Treffer gewinnt.
 *  3. Fallback: Einnahmen -> "Einkommen", sonst Kategorie offen lassen.
 *
 * Lädt die Regeln einmal pro Instanz (ein Import-Lauf = eine Instanz).
 */
class RuleEngine
{
    /** @var Collection<int,CategoryRule> */
    private Collection $rules;

    private ?int $incomeCategoryId;

    public function __construct()
    {
        $this->rules = CategoryRule::query()->byPriority()->get();
        $this->incomeCategoryId = Category::where('name', 'Einkommen')->value('id');
    }

    /**
     * @return array{category_id: int|null, is_internal_transfer: bool, type: string}
     */
    public function classify(TransactionData $t, ?string $accountType = null): array
    {
        $haystack = mb_strtoupper(($t->counterparty ?? '').' '.($t->description ?? '').' '.$t->rawText);

        if (preg_match('/KARTENABRECHNUNG|HANSEATIC BANK/u', $haystack)) {
            return ['category_id' => null, 'is_internal_transfer' => true, 'type' => 'transfer'];
        }

        foreach ($this->rules as $rule) {
            if ($rule->account_type !== null && $rule->account_type !== $accountType) {
                continue;
            }
            if ($rule->matchesValue($this->fieldValue($t, $rule->field))) {
                return ['category_id' => $rule->category_id, 'is_internal_transfer' => false, 'type' => $t->type];
            }
        }

        if ($t->type === 'income') {
            return ['category_id' => $this->incomeCategoryId, 'is_internal_transfer' => false, 'type' => 'income'];
        }

        return ['category_id' => null, 'is_internal_transfer' => false, 'type' => $t->type];
    }

    private function fieldValue(TransactionData $t, string $field): ?string
    {
        return match ($field) {
            'counterparty' => $t->counterparty,
            'description' => $t->description,
            'raw_text' => $t->rawText,
            default => null,
        };
    }
}
