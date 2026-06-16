<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    protected $fillable = [
        'debt_id', 'due_date', 'amount_cents', 'paid', 'paid_date', 'transaction_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount_cents' => 'integer',
        'paid' => 'boolean',
    ];

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
