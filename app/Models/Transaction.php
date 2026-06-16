<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'account_id', 'booking_date', 'value_date', 'amount_cents', 'currency',
        'counterparty', 'description', 'raw_text', 'category_id',
        'payment_method', 'type', 'is_internal_transfer', 'is_manual', 'dedup_hash',
        'import_batch_id',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'value_date' => 'date',
        'amount_cents' => 'integer',
        'is_internal_transfer' => 'boolean',
        'is_manual' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** Echte Ausgaben (ohne interne Transfers). */
    public function scopeRealExpenses(Builder $query): Builder
    {
        return $query->where('type', 'expense')->where('is_internal_transfer', false);
    }

    /** Echte Einnahmen (ohne interne Transfers). */
    public function scopeRealIncome(Builder $query): Builder
    {
        return $query->where('type', 'income')->where('is_internal_transfer', false);
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('booking_date', $year)
            ->whereMonth('booking_date', $month);
    }
}
