<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionEditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id', 'edited_by', 'old_data', 'new_data',
        'action', 'ip_address', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'old_data'  => 'array',
            'new_data'  => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
