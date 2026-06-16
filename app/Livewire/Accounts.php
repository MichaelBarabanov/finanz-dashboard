<?php

namespace App\Livewire;

use App\Models\Account;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Konten')]
class Accounts extends Component
{
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:giro,credit_card')]
    public string $type = 'giro';

    #[Validate('nullable|string|max:255')]
    public ?string $bank = null;

    #[Validate('nullable|string|max:4')]
    public ?string $iban_last4 = null;

    #[Validate('nullable|string|max:30')]
    public ?string $opening_balance = '';

    #[Validate('nullable|string|max:30')]
    public ?string $credit_limit = '';

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'bank' => $this->bank ?: null,
            'iban_last4' => $this->iban_last4 ?: null,
            'opening_balance_cents' => euros_to_cents($this->opening_balance ?? ''),
            'credit_limit_cents' => $this->type === 'credit_card' && $this->credit_limit !== ''
                ? euros_to_cents($this->credit_limit)
                : null,
        ];

        if ($this->editingId) {
            Account::findOrFail($this->editingId)->update($data);
        } else {
            Account::create($data);
        }

        $this->resetForm();
        session()->flash('saved', 'Konto gespeichert.');
    }

    public function edit(int $id): void
    {
        $a = Account::findOrFail($id);
        $this->editingId = $a->id;
        $this->name = $a->name;
        $this->type = $a->type;
        $this->bank = $a->bank;
        $this->iban_last4 = $a->iban_last4;
        $this->opening_balance = number_format($a->opening_balance_cents / 100, 2, ',', '');
        $this->credit_limit = $a->credit_limit_cents !== null
            ? number_format($a->credit_limit_cents / 100, 2, ',', '')
            : '';
    }

    public function delete(int $id): void
    {
        Account::findOrFail($id)->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        session()->flash('saved', 'Konto gelöscht.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'type', 'bank', 'iban_last4', 'opening_balance', 'credit_limit']);
        $this->type = 'giro';
    }

    public function render()
    {
        return view('livewire.accounts', [
            'accounts' => Account::orderBy('name')->get(),
        ]);
    }
}
