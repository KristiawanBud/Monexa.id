<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AiChatSession;
use App\Services\CuanAiService;
use Illuminate\Http\Request;

class CuanAiController extends Controller
{
    public function __construct(private CuanAiService $cuanAi) {}

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $user = $request->user();

        $session = AiChatSession::firstOrCreate(
            ['user_id' => $user->id],
            ['messages' => [], 'message_count' => 0]
        );

        // Ambil waktu chat terakhir SEBELUM ke-update oleh pesan baru ini
        $previousLastMessageAt = $session->last_message_at;

        $userMessage = $request->message;

        $session->addMessage('user', $userMessage);

        $result = $this->cuanAi->chat($user, $userMessage, $session, $previousLastMessageAt);

        $session->addMessage('assistant', $result['reply']);

        return response()->json([
            'reply' => $result['reply'],
            'timestamp' => now()->format('H:i'),
            'action_taken' => $result['action_taken'] ?? false,
        ]);
    }

    public function history(Request $request)
    {
        $session = AiChatSession::where('user_id', $request->user()->id)->first();

        return response()->json([
            'messages' => $session?->getLastNMessages(20) ?? [],
        ]);
    }

    public function reset(Request $request)
    {
        AiChatSession::where('user_id', $request->user()->id)
            ->update(['messages' => '[]', 'message_count' => 0]);

        return response()->json(['success' => true]);
    }
}
