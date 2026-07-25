<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'name', 'emoji', 'type', 'amount', 'due_day', 'due_date', 'category_id', 'remind_days', 'notif_wa_enabled', 'note', 'is_active', 'last_paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date', 'last_paid_at' => 'date', 'remind_days' => 'array', 'notif_wa_enabled' => 'boolean', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if ($this->type === 'recurring' && $this->due_day) {
            $dueDate = Carbon::now()->day($this->due_day);
            if ($dueDate->isPast()) {
                $dueDate->addMonth();
            }

            return (int) Carbon::today()->diffInDays($dueDate, false);
        }
        if ($this->type === 'one_time' && $this->due_date) {
            return (int) Carbon::today()->diffInDays($this->due_date, false);
        }

        return null;
    }

    public function getStatusColorAttribute(): string
    {
        $days = $this->days_until_due;
        if ($days === null) {
            return 'stone';
        }
        if ($days < 0) {
            return 'red';
        }
        if ($days <= 3) {
            return 'amber';
        }

        return 'green';
    }
}
