<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\DebtPlanner;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Schulden')]
class Debts extends Component
{
    // --- Formular: neue Schuld ---
    #[Validate('required|string|max:191')]
    public string $name = '';

    #[Validate('required|in:installment,revolving')]
    public string $type = 'installment';

    #[Validate('required|string')]
    public string $total_amount = '';

    #[Validate('nullable|integer|min:1|max:600')]
    public ?int $installment_count = null;

    #[Validate('nullable|string')]
    public ?string $monthly_amount = null;

    #[Validate('required|integer|min:1|max:31')]
    public int $payment_day = 1;

    #[Validate('required|date')]
    public string $start_date = '';

    #[Validate('nullable|exists:accounts,id')]
    public $linked_account_id = null;

    // --- Tilgung erfassen (revolving) ---
    public ?int $payDebtId = null;
    public string $payAmount = '';
    public string $payDate = '';

    public function mount(): void
    {
        $this->start_date = Carbon::now()->format('Y-m-d');
        $this->payDate = Carbon::now()->format('Y-m-d');
    }

    public function addDebt(): void
    {
        $this->validate();

        $debt = Debt::create([
            'name' => $this->name,
            'type' => $this->type,
            'total_amount_cents' => euros_to_cents($this->total_amount),
            'installment_count' => $this->type === 'installment' ? $this->installment_count : null,
            'monthly_amount_cents' => $this->monthly_amount ? euros_to_cents($this->monthly_amount) : null,
            'payment_day' => $this->payment_day,
            'start_date' => $this->start_date,
            'linked_account_id' => $this->linked_account_id ?: null,
            'status' => 'active',
        ]);

        if ($debt->isInstallment()) {
            $planner = new DebtPlanner();
            $planner->generate($debt);
            $planner->markPastDuePaid($debt->fresh());
        }

        $this->reset(['name', 'total_amount', 'installment_count', 'monthly_amount', 'linked_account_id']);
        $this->type = 'installment';
        $this->payment_day = 1;
        session()->flash('saved', 'Schuld angelegt.');
    }

    public function togglePaid(int $paymentId): void
    {
        $p = DebtPayment::findOrFail($paymentId);
        $p->update([
            'paid' => ! $p->paid,
            'paid_date' => ! $p->paid ? Carbon::today()->toDateString() : null,
        ]);
    }

    public function startPayment(int $debtId): void
    {
        $this->payDebtId = $debtId;
        $this->payAmount = '';
        $this->payDate = Carbon::now()->format('Y-m-d');
    }

    public function recordPayment(): void
    {
        $this->validate([
            'payAmount' => 'required|string',
            'payDate' => 'required|date',
        ]);

        $cents = abs(euros_to_cents($this->payAmount));
        if ($cents <= 0 || ! $this->payDebtId) {
            return;
        }

        DebtPayment::create([
            'debt_id' => $this->payDebtId,
            'due_date' => $this->payDate,
            'amount_cents' => $cents,
            'paid' => true,
            'paid_date' => $this->payDate,
        ]);

        $this->payDebtId = null;
        $this->payAmount = '';
        session()->flash('saved', 'Tilgung erfasst.');
    }

    public function deleteDebt(int $id): void
    {
        Debt::findOrFail($id)->delete();
        session()->flash('saved', 'Schuld gelöscht.');
    }

    public function render()
    {
        $debts = Debt::with(['payments', 'linkedAccount'])
            ->orderByRaw("status = 'active' desc")
            ->orderBy('name')
            ->get();

        $active = $debts->where('status', 'active');

        return view('livewire.debts', [
            'debts' => $debts,
            'accounts' => Account::orderBy('name')->get(),
            'totalRemainingCents' => (int) $active->sum(fn ($d) => $d->remaining_cents),
            'totalMonthlyCents' => (int) $active->sum(fn ($d) => $d->monthly_load_cents),
            'totalPaidCents' => (int) $active->sum(fn ($d) => $d->paid_cents),
        ]);
    }
}
