<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingGoal extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'name', 'emoji', 'target_amount', 'current_amount', 'deadline', 'status'];

    protected function casts(): array
    {
        return ['target_amount' => 'decimal:2', 'current_amount' => 'decimal:2', 'deadline' => 'date', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(SavingDeposit::class);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return min(100, round(($this->current_amount / $this->target_amount) * 100, 1));
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }
}
