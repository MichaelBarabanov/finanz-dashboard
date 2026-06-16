<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'name', 'type', 'bank', 'iban_last4',
        'opening_balance_cents', 'credit_limit_cents',
        'currency', 'is_active',
    ];

    protected $casts = [
        'opening_balance_cents' => 'integer',
        'credit_limit_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Aktueller Saldo = Anfangssaldo + Summe aller Buchungen.
     * Abgeleitet statt gespeichert -> kann nie inkonsistent werden.
     */
    public function getBalanceCentsAttribute(): int
    {
        $sum = $this->relationLoaded('transactions')
            ? $this->transactions->sum('amount_cents')
            : (int) $this->transactions()->sum('amount_cents');

        return (int) $this->opening_balance_cents + (int) $sum;
    }

    /** Bei Kreditkarte: noch verfügbarer Rahmen. */
    public function getAvailableCreditCentsAttribute(): ?int
    {
        if ($this->type !== 'credit_card' || $this->credit_limit_cents === null) {
            return null;
        }

        // Saldo ist bei verbrauchter Karte negativ; Rahmen = Limit + Saldo.
        return (int) $this->credit_limit_cents + $this->balance_cents;
    }

    public function isCreditCard(): bool
    {
        return $this->type === 'credit_card';
    }
}
