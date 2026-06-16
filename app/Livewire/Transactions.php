<?php

namespace App\Livewire;

use App\Imports\RuleLearner;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Korrektur-Ansicht: importierte Buchungen prüfen und umkategorisieren.
 * Bewusst KEINE manuelle Erfassung — das Tool wird über PDF-Import gefüttert,
 * nicht durch Handeingabe einzelner Ausgaben.
 */
#[Title('Korrektur')]
class Transactions extends Component
{
    use WithPagination;

    // Filter
    public $filterAccount = '';
    public string $filterMonth = '';

    // Regel-Lernen (Prompt nach manueller Umkategorisierung)
    public ?array $learn = null;
    public string $learnPattern = '';

    public function mount(): void
    {
        $this->filterMonth = Carbon::now()->format('Y-m');
    }

    public function setCategory(int $transactionId, $categoryId): void
    {
        $t = Transaction::findOrFail($transactionId);
        $t->category_id = $categoryId ?: null;
        $t->save();

        if ($categoryId && $t->counterparty) {
            $this->learn = [
                'category_id' => (int) $categoryId,
                'category_name' => Category::whereKey($categoryId)->value('name'),
                'counterparty' => $t->counterparty,
            ];
            $this->learnPattern = RuleLearner::suggestPattern($t->counterparty);
        } else {
            $this->dismissLearn();
        }
    }

    public function confirmLearn(): void
    {
        if (! $this->learn || trim($this->learnPattern) === '') {
            return;
        }
        $res = (new RuleLearner())->learn('counterparty', $this->learnPattern, $this->learn['category_id']);
        session()->flash('saved', "Regel „{$this->learnPattern}“ → {$this->learn['category_name']} angelegt · {$res['affected']} Buchungen aktualisiert.");
        $this->dismissLearn();
    }

    public function dismissLearn(): void
    {
        $this->learn = null;
        $this->learnPattern = '';
    }

    public function delete(int $id): void
    {
        Transaction::findOrFail($id)->delete();
        session()->flash('saved', 'Buchung gelöscht.');
    }

    public function updatingFilterAccount(): void
    {
        $this->resetPage();
    }

    public function updatingFilterMonth(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Transaction::with(['account', 'category'])
            ->orderByDesc('booking_date')
            ->orderByDesc('id');

        if ($this->filterAccount !== '') {
            $query->where('account_id', $this->filterAccount);
        }
        if ($this->filterMonth !== '') {
            [$y, $m] = explode('-', $this->filterMonth);
            $query->inMonth((int) $y, (int) $m);
        }

        return view('livewire.transactions', [
            'transactions' => $query->paginate(25),
            'accounts' => Account::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
