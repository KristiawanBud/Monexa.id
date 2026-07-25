<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WaGateway;
use App\Models\WaMessageLog;
use App\Services\WaGatewayService;
use App\Services\WaParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private WaParserService $waParser,
        private WaGatewayService $gatewayService
    ) {}

    /**
     * ─────────────────────────────────────────────────────────
     * ENDPOINT UTAMA — Dipanggil oleh n8n setiap ada pesan WA masuk.
     *
     * Route: POST /webhook/whatsapp
     * Auth : Header X-Webhook-Secret harus cocok dengan N8N_WEBHOOK_SECRET
     *
     * Payload yang diharapkan dari n8n (sesuaikan node "HTTP Request"
     * di workflow n8n supaya body JSON-nya seperti ini):
     *
     * {
     *   "sender": "628123456789",        // nomor pengirim pesan
     *   "receiver": "628987654321",      // nomor bot yang menerima (gateway)
     *   "message": "Makan siang 35rb",   // isi pesan teks
     *   "type": "text",                  // "text" atau "image"
     *   "image_url": null,               // URL gambar jika type = image
     *   "message_id": "abc123"           // ID unik pesan dari Fonnte (opsional)
     * }
     *
     * Response yang dikembalikan ke n8n:
     * {
     *   "success": true,
     *   "reply": "✅ Pemasukan Tercatat!...",
     *   "should_send": true
     * }
     *
     * n8n tinggal ambil field "reply" dan kirim balik via node Fonnte/HTTP Request.
     * ─────────────────────────────────────────────────────────
     */
    public function receive(Request $request): JsonResponse
    {
        // ── Validasi secret key dari n8n ──
        if (! $this->verifyWebhookSecret($request)) {
            Log::warning('WhatsAppWebhook: Invalid secret key from '.$request->ip());

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'sender' => ['required', 'string'],
            'message' => ['nullable', 'string'],
            'type' => ['nullable', 'in:text,image'],
            'image_url' => ['nullable', 'string'],
        ]);

        $senderNumber = $this->normalizePhoneNumber($request->sender);
        $messageText = $request->message ?? '';
        $imageUrl = $request->type === 'image' ? $request->image_url : null;

        // ── Cari user berdasarkan nomor WA pengirim ──
        $user = User::where('wa_number', $senderNumber)
            ->orWhere('wa_number', '0'.substr($senderNumber, 2)) // handle format 08xx vs 628xx
            ->first();

        if (! $user) {
            Log::info("WhatsAppWebhook: Nomor {$senderNumber} tidak terdaftar di sistem.");

            return response()->json([
                'success' => true,
                'reply' => "👋 Halo! Nomor WA kamu belum terdaftar di CatatCuan.\n\nSilakan daftar dulu di aplikasi, lalu isi nomor WA ini di halaman Profil.",
                'should_send' => true,
            ]);
        }

        // ── Log pesan masuk ──
        $log = WaMessageLog::create([
            'user_id' => $user->id,
            'direction' => 'incoming',
            'from_number' => $senderNumber,
            'message' => $messageText ?: '[Gambar]',
            'status' => 'pending',
            'received_at' => now(),
        ]);

        try {
            // ── Proses pesan via WaParserService ──
            $reply = $this->waParser->handleIncomingMessage($user, $messageText, $imageUrl);

            $log->update(['status' => 'processed']);

            // ── Update last_used_at gateway (untuk monitoring) ──
            $this->touchGatewayUsage($request->receiver ?? null);

            return response()->json([
                'success' => true,
                'reply' => $reply,
                'should_send' => true,
                'user_id' => $user->id,
            ]);

        } catch (\Exception $e) {
            Log::error("WhatsAppWebhook error untuk user {$user->id}: {$e->getMessage()}");

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true, // tetap true supaya n8n tidak retry terus-menerus
                'reply' => '⚠️ Maaf, terjadi kesalahan saat memproses pesanmu. Tim kami akan segera memperbaikinya.',
                'should_send' => true,
            ]);
        }
    }

    /**
     * ─────────────────────────────────────────────────────────
     * ENDPOINT TEST — Untuk verifikasi koneksi n8n ↔ Laravel
     * sebelum integrasi penuh. n8n bisa panggil ini dulu untuk
     * memastikan webhook URL dan secret sudah benar.
     *
     * Route: GET /webhook/whatsapp/ping
     * ─────────────────────────────────────────────────────────
     */
    public function ping(Request $request): JsonResponse
    {
        if (! $this->verifyWebhookSecret($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'CatatCuan webhook siap menerima pesan dari n8n!',
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * ─────────────────────────────────────────────────────────
     * ENDPOINT STATUS GATEWAY — n8n bisa polling endpoint ini
     * untuk tahu nomor mana yang harus dipakai kirim balasan
     * ke user tertentu (sesuai assignment Model A: 1 user = 1 nomor).
     *
     * Route: GET /webhook/whatsapp/gateway-for/{userId}
     * ─────────────────────────────────────────────────────────
     */
    public function gatewayForUser(Request $request, string $userId): JsonResponse
    {
        if (! $this->verifyWebhookSecret($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = User::find($userId);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        $gateway = $user->activeGateway();
        if (! $gateway) {
            return response()->json(['success' => false, 'message' => 'User belum punya gateway aktif'], 404);
        }

        return response()->json([
            'success' => true,
            'gateway_number' => $gateway->phone_number,
            'fonnte_token' => $gateway->fonnte_token,
            'user_wa_number' => $user->wa_number,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────

    private function verifyWebhookSecret(Request $request): bool
    {
        $secret = $request->header('X-Webhook-Secret') ?? $request->input('webhook_secret');
        $expected = config('services.n8n.webhook_secret', env('N8N_WEBHOOK_SECRET'));

        // Kalau secret belum di-set di .env, tolak semua (fail-safe)
        if (empty($expected)) {
            Log::warning('WhatsAppWebhook: N8N_WEBHOOK_SECRET belum di-set di .env!');

            return false;
        }

        return hash_equals($expected, (string) $secret);
    }

    private function normalizePhoneNumber(string $number): string
    {
        // Hilangkan karakter non-digit
        $number = preg_replace('/\D/', '', $number);

        // Normalisasi 08xx → 628xx
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return $number;
    }

    private function touchGatewayUsage(?string $receiverNumber): void
    {
        if (! $receiverNumber) {
            return;
        }

        $normalized = $this->normalizePhoneNumber($receiverNumber);

        WaGateway::where('phone_number', $normalized)->update([
            'last_used_at' => now(),
            'total_sent_today' => \DB::raw('total_sent_today + 1'),
            'total_sent_all' => \DB::raw('total_sent_all + 1'),
        ]);
    }
}
