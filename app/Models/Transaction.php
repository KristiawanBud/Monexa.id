<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id', 'wallet_id', 'category_id', 'type',
        'amount', 'note', 'transacted_at', 'source',
        'wa_message_id', 'bill_payment_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'transacted_at'  => 'date',
        ];
    }

    // ── Relations ──
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(UserWallet::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──
    public function scopeIncome($q)     { return $q->where('type', 'income'); }
    public function scopeExpense($q)    { return $q->where('type', 'expense'); }

    public function scopeThisMonth($q)
    {
        return $q->whereMonth('transacted_at', now()->month)
                 ->whereYear('transacted_at', now()->year);
    }

    public function scopeForPeriod($q, string $period)
    {
        [$year, $month] = explode('-', $period);
        return $q->whereYear('transacted_at', $year)
                 ->whereMonth('transacted_at', $month);
    }

    // ── Helpers ──
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format((float)$this->amount, 0, ',', '.');
    }
}
