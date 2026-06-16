<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    protected $fillable = [
        'name', 'type', 'total_amount_cents', 'installment_count',
        'monthly_amount_cents', 'payment_day', 'start_date',
        'interest_rate', 'linked_account_id', 'status',
    ];

    protected $casts = [
        'total_amount_cents' => 'integer',
        'installment_count' => 'integer',
        'monthly_amount_cents' => 'integer',
        'payment_day' => 'integer',
        'start_date' => 'date',
        'interest_rate' => 'decimal:2',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class)->orderBy('due_date');
    }

    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'linked_account_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isInstallment(): bool
    {
        return $this->type === 'installment';
    }

    /** Bisher getilgt (Summe bezahlter Raten/Tilgungen). */
    public function getPaidCentsAttribute(): int
    {
        return (int) $this->payments->where('paid', true)->sum('amount_cents');
    }

    /** Offene Restschuld = Gesamt − getilgt. Nie < 0. */
    public function getRemainingCentsAttribute(): int
    {
        return max(0, (int) $this->total_amount_cents - $this->paid_cents);
    }

    /** Fortschritt 0..100. */
    public function getProgressPercentAttribute(): int
    {
        if ($this->total_amount_cents <= 0) {
            return 100;
        }

        return (int) min(100, round($this->paid_cents / $this->total_amount_cents * 100));
    }

    /** Verbleibende offene Raten (nur installment sinnvoll). */
    public function getInstallmentsRemainingAttribute(): int
    {
        return $this->payments->where('paid', false)->count();
    }

    /** Nächste fällige (unbezahlte) Rate. */
    public function getNextPaymentAttribute(): ?DebtPayment
    {
        return $this->payments->where('paid', false)->sortBy('due_date')->first();
    }

    /** Voraussichtliches Enddatum = Fälligkeit der letzten offenen Rate. */
    public function getProjectedEndAttribute(): ?\Illuminate\Support\Carbon
    {
        $last = $this->payments->where('paid', false)->sortByDesc('due_date')->first();

        return $last?->due_date;
    }

    /**
     * Erwartete monatliche Belastung.
     * installment: feste Rate. revolving: hinterlegte monatliche Tilgung (falls gesetzt).
     */
    public function getMonthlyLoadCentsAttribute(): int
    {
        if ($this->remaining_cents <= 0) {
            return 0;
        }

        return (int) ($this->monthly_amount_cents ?? 0);
    }
}
