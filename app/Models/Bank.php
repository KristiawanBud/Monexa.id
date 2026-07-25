<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'short_name', 'type', 'logo_color', 'logo_initial', 'logo_url', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(UserWallet::class);
    }
}
