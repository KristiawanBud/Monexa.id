<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaGatewayLog extends Model
{
    protected $table = 'wa_gateway_logs';

    public $timestamps = false;

    protected $fillable = [
        'gateway_id', 'user_id', 'to_number',
        'type', 'status', 'error_message', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(WaGateway::class, 'gateway_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
