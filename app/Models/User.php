<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUlids, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'google_id',
        'wa_number', 'role', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'wa_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ── Standard Relations ─────────────────────────
    /**
     * @return HasOne<UserProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(UserWallet::class)->orderBy('sort_order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function savingGoals(): HasMany
    {
        return $this->hasMany(SavingGoal::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(UserAsset::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function receiptScans(): HasMany
    {
        return $this->hasMany(ReceiptScan::class);
    }

    // ── WA Gateway Relations ───────────────────────
    public function waGatewayAssignment(): HasOne
    {
        return $this->hasOne(UserWaGateway::class)->where('status', 'active');
    }

    public function waGatewayHistory(): HasMany
    {
        return $this->hasMany(UserWaGateway::class);
    }

    public function activeGateway(): ?WaGateway
    {
        return $this->waGatewayAssignment?->gateway;
    }

    // ── Helpers ────────────────────────────────────
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hasActiveSubscription(): bool
    {
        $sub = $this->subscription;
        if (! $sub) {
            return false;
        }
        if ($sub->plan === 'trial' && $sub->trial_ends_at?->isPast()) {
            return false;
        }

        return $sub->status === 'active';
    }

    public function totalBalance(): float
    {
        return (float) $this->wallets()->where('is_active', true)->sum('balance');
    }

    public function activeWallets()
    {
        return $this->wallets()->where('is_active', true)->get();
    }
}
