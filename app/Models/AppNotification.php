<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasUlids;

    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = ['user_id', 'type', 'title', 'body', 'reference_type', 'reference_id', 'is_read', 'wa_sent', 'read_at'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'wa_sent' => 'boolean', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
