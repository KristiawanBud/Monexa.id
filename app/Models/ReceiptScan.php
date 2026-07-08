<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptScan extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'image_url', 'parsed_result',
        'status', 'transaction_id', 'ai_provider', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'parsed_result' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
