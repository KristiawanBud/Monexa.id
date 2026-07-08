<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WaGateway extends Model
{
    protected $table = 'wa_gateways';

    protected $fillable = [
        'name', 'phone_number', 'fonnte_token', 'fonnte_device_id',
        'max_users', 'current_users', 'status', 'status_note',
        'total_sent_today', 'total_sent_all',
        'last_reset_at', 'last_used_at',
        'last_ping_at', 'is_connected', 'disconnected_at', 'owner_wa_number',
        'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default'      => 'boolean',
            'is_connected'    => 'boolean',
            'last_reset_at'   => 'datetime',
            'last_used_at'    => 'datetime',
            'last_ping_at'    => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    // Menit sejak terakhir aktif (untuk deteksi disconnect)
    public function getMinutesSinceLastPingAttribute(): ?int
    {
        if (! $this->last_ping_at) return null;
        return (int) $this->last_ping_at->diffInMinutes(now());
    }

    // Threshold: jika > 30 menit tidak ada aktivitas, anggap disconnect
    public function getIsPossiblyDisconnectedAttribute(): bool
    {
        if (! $this->is_connected) return true;
        if (! $this->last_ping_at) return false;
        return $this->last_ping_at->diffInMinutes(now()) > 30;
    }

    // Warna CSS sesuai design system monokromatik app.css
    public function getStatusCssClassAttribute(): string
    {
        if (! $this->is_connected || $this->is_possibly_disconnected) {
            return 'text-red';
        }
        return match ($this->status) {
            'active'    => 'text-green',
            'warning'   => 'text-amber',
            'suspended' => 'text-red',
            'inactive'  => 'text-muted',
            default     => 'text-muted',
        };
    }

    // ── Relations ──────────────────────────────────
    public function userGateways(): HasMany
    {
        return $this->hasMany(UserWaGateway::class, 'gateway_id');
    }

    public function activeUsers(): HasMany
    {
        return $this->hasMany(UserWaGateway::class, 'gateway_id')
            ->where('status', 'active');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WaGatewayLog::class, 'gateway_id');
    }

    // ── Computed ───────────────────────────────────
    public function getSlotAvailableAttribute(): int
    {
        return max(0, $this->max_users - $this->current_users);
    }

    public function getUsagePercentAttribute(): float
    {
        if ($this->max_users <= 0) return 0;
        return round(($this->current_users / $this->max_users) * 100, 1);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active'    => 'green',
            'warning'   => 'amber',
            'suspended' => 'red',
            'inactive'  => 'stone',
            default     => 'stone',
        };
    }

    public function getIsFull(): bool
    {
        return $this->current_users >= $this->max_users;
    }

    // ── Scopes ─────────────────────────────────────
    public function scopeAvailable($q)
    {
        return $q->where('status', 'active')
                 ->whereColumn('current_users', '<', 'max_users')
                 ->orderBy('sort_order')
                 ->orderBy('current_users'); // assign ke yang paling sedikit user dulu
    }

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['active', 'warning']);
    }
}
