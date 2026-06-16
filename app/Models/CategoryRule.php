<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRule extends Model
{
    protected $fillable = [
        'field', 'match_type', 'pattern', 'category_id',
        'account_type', 'priority', 'auto_created',
    ];

    protected $casts = [
        'priority' => 'integer',
        'auto_created' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Höchste Priorität zuerst; bei Gleichstand ältere Regel zuerst (stabil). */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderBy('id');
    }

    /** Passt der übergebene Feldwert auf diese Regel? Vergleich case-insensitive. */
    public function matchesValue(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $v = mb_strtolower($value);
        $p = mb_strtolower(trim($this->pattern));

        if ($p === '') {
            return false;
        }

        return match ($this->match_type) {
            'contains' => str_contains($v, $p),
            'starts_with' => str_starts_with($v, $p),
            'exact' => $v === $p,
            'regex' => @preg_match('/'.$this->pattern.'/iu', $value) === 1,
            default => false,
        };
    }
}
