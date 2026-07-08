<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'slug', 'billing_period', 'price',
        'duration_days', 'features', 'is_active', 'sort_order',
        'discount_type', 'discount_value', 'discount_label',
        'discount_starts_at', 'discount_ends_at',
    ];

    protected $appends = ['has_active_discount', 'final_price'];

    protected function casts(): array
    {
        return [
            'price'              => 'decimal:2',
            'discount_value'     => 'decimal:2',
            'features'           => 'array',
            'is_active'          => 'boolean',
            'discount_starts_at' => 'datetime',
            'discount_ends_at'   => 'datetime',
        ];
    }

    // ── Apakah diskonnya sedang aktif (cek periode kalau diisi) ──
    public function getHasActiveDiscountAttribute(): bool
    {
        if (!$this->discount_type || !$this->discount_value) {
            return false;
        }

        $now = now();

        if ($this->discount_starts_at && $now->lt($this->discount_starts_at)) {
            return false;
        }
        if ($this->discount_ends_at && $now->gt($this->discount_ends_at)) {
            return false;
        }

        return true;
    }

    // ── Harga setelah dipotong diskon (kalau aktif) ──
    public function getFinalPriceAttribute(): float
    {
        if (!$this->has_active_discount) {
            return (float) $this->price;
        }

        $discountAmount = $this->discount_type === 'percent'
            ? (float) $this->price * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return max(0, (float) $this->price - $discountAmount);
    }
}
