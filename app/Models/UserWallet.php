<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserWallet extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['user_id', 'bank_id', 'display_name', 'account_number', 'type', 'balance', 'initial_balance', 'is_active', 'is_primary', 'currency', 'is_saham', 'saham_modal', 'saham_nilai_sekarang', 'sort_order'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2', 'initial_balance' => 'decimal:2', 'saham_modal' => 'decimal:2', 'saham_nilai_sekarang' => 'decimal:2', 'is_active' => 'boolean', 'is_primary' => 'boolean', 'is_saham' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'wallet_id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(WalletTransfer::class, 'from_wallet_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(WalletTransfer::class, 'to_wallet_id');
    }

    public function getBankColorAttribute(): string
    {
        return $this->bank?->logo_color ?? '#2563EB';
    }

    public function getBankInitialAttribute(): string
    {
        return $this->bank?->logo_initial ?? substr($this->display_name, 0, 1);
    }
}
