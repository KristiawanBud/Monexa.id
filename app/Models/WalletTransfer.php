<?php

namespace App\Models;

use App\Enums\WalletTransferStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property WalletTransferStatus $status
 */
class WalletTransfer extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id', 'from_wallet_id', 'to_wallet_id',
        'amount', 'note', 'transferred_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transferred_at' => 'datetime',
            'status' => WalletTransferStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(UserWallet::class, 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(UserWallet::class, 'to_wallet_id');
    }
}
