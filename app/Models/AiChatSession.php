<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatSession extends Model
{
    use HasUlids;

    protected $table = 'ai_chat_sessions';

    protected $fillable = [
        'user_id', 'messages', 'message_count', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role, // 'user' | 'assistant'
            'content' => $content,
            'timestamp' => now()->toISOString(),
        ];

        // Simpan max 20 pesan terakhir (10 pasang)
        if (count($messages) > 20) {
            $messages = array_slice($messages, -20);
        }

        $this->update([
            'messages' => $messages,
            'message_count' => $this->message_count + 1,
            'last_message_at' => now(),
        ]);
    }

    public function getLastNMessages(int $n = 10): array
    {
        $messages = $this->messages ?? [];

        return array_slice($messages, -$n);
    }
}
