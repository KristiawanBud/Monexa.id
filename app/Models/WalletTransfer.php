<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransfer extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'from_wallet_id', 'to_wallet_id',
        'amount', 'fee', 'note', 'category_id', 'transferred_at', 'request_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'transferred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<UserWallet, $this>
     */
    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(UserWallet::class, 'from_wallet_id');
    }

    /**
     * @return BelongsTo<UserWallet, $this>
     */
    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(UserWallet::class, 'to_wallet_id');
    }

    /**
     * @return BelongsTo<TransactionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }
}
