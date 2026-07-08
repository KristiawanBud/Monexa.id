<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessageLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'direction', 'from_number', 'message',
        'intent', 'parsed_data', 'status', 'error_message', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed_data' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
