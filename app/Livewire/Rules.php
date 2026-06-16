<?php

namespace App\Livewire;

use App\Imports\RuleLearner;
use App\Models\Category;
use App\Models\CategoryRule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Regeln')]
class Rules extends Component
{
    #[Validate('required|string|max:191')]
    public string $pattern = '';

    #[Validate('required|in:counterparty,description,raw_text')]
    public string $field = 'raw_text';

    #[Validate('required|in:contains,starts_with,exact,regex')]
    public string $match_type = 'contains';

    #[Validate('required|exists:categories,id')]
    public $category_id = null;

    #[Validate('nullable|in:giro,credit_card')]
    public ?string $account_type = null;

    #[Validate('required|integer|min:0|max:1000')]
    public int $priority = 50;

    public function save(): void
    {
        $this->validate();

        // Über RuleLearner anlegen, damit die Regel direkt rückwirkend greift.
        $res = (new RuleLearner())->learn(
            $this->field,
            $this->pattern,
            (int) $this->category_id,
            $this->account_type ?: null,
            $this->match_type,
        );
        // Priorität aus dem Formular übernehmen (Learner setzt sonst 100).
        $res['rule']->update(['priority' => $this->priority, 'auto_created' => false]);

        $this->reset(['pattern', 'account_type']);
        $this->field = 'raw_text';
        $this->match_type = 'contains';
        $this->priority = 50;

        session()->flash('saved', "Regel gespeichert · {$res['affected']} bestehende Buchungen aktualisiert.");
    }

    public function applyNow(int $ruleId): void
    {
        $rule = CategoryRule::findOrFail($ruleId);
        $n = (new RuleLearner())->applyRetroactively($rule);
        session()->flash('saved', "{$n} Buchungen nach dieser Regel aktualisiert.");
    }

    public function delete(int $id): void
    {
        CategoryRule::findOrFail($id)->delete();
        session()->flash('saved', 'Regel gelöscht.');
    }

    public function render()
    {
        return view('livewire.rules', [
            'rules' => CategoryRule::with('category')->byPriority()->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
