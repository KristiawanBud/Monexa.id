<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWaGateway extends Model
{
    protected $table = 'user_wa_gateways';

    protected $fillable = [
        'user_id', 'gateway_id', 'status',
        'assigned_at', 'released_at', 'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(WaGateway::class, 'gateway_id');
    }
}
