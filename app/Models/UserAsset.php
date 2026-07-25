<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAsset extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'name', 'emoji', 'type',
        'value', 'note', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'liquid' => 'Aset Likuid',
            'fixed' => 'Aset Tetap',
            'investment' => 'Investasi',
            'receivable' => 'Piutang',
            default => $this->type,
        };
    }
}
