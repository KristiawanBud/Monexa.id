<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use App\Models\UserWaGateway;
use App\Models\WaGateway;
use App\Models\WaGatewayLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGatewayService
{
    // ────────────────────────────────────────────────
    // ASSIGN: Pasangkan user ke gateway tersedia
    // ────────────────────────────────────────────────
    public function assignGateway(User $user): ?WaGateway
    {
        if (! $user->wa_number) {
            return null;
        }

        // Cek sudah punya assignment aktif
        $existing = UserWaGateway::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('gateway')
            ->first();

        if ($existing) {
            return $existing->gateway;
        }

        return DB::transaction(function () use ($user) {
            $gateway = WaGateway::available()->lockForUpdate()->first();

            if (! $gateway) {
                Log::warning("WaGatewayService: Tidak ada gateway tersedia untuk user {$user->id}");
                // Kirim notif ke admin
                $this->notifyAdminNoSlot();

                return null;
            }

            UserWaGateway::create([
                'user_id' => $user->id,
                'gateway_id' => $gateway->id,
                'status' => 'active',
                'assigned_at' => now(),
            ]);

            $gateway->increment('current_users');
            $this->updateGatewayStatus($gateway->fresh());

            Log::info("WaGatewayService: User {$user->id} assigned ke gateway {$gateway->id}");

            // ① Kirim WA sambutan + info nomor bot
            $this->sendWelcomeMessage($user, $gateway->fresh());

            // ② Simpan notif in-app
            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'gateway_assigned',
                'title' => '📱 Nomor Bot WA Kamu Siap!',
                'body' => "Simpan nomor ini di kontakmu: {$gateway->phone_number}\nKirim pesan apa saja ke nomor ini untuk mulai mencatat keuangan.",
            ]);

            return $gateway->fresh();
        });
    }

    // ────────────────────────────────────────────────
    // RELEASE: Bebaskan slot gateway dari user
    // ────────────────────────────────────────────────
    public function releaseGateway(User $user, string $reason = 'manual'): bool
    {
        $assignment = UserWaGateway::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $assignment) {
            return false;
        }

        return DB::transaction(function () use ($assignment, $reason, $user) {
            $assignment->update([
                'status' => 'released',
                'released_at' => now(),
                'release_reason' => $reason,
            ]);

            $gateway = WaGateway::find($assignment->gateway_id);
            $gateway->decrement('current_users');
            if ($gateway->current_users < 0) {
                $gateway->update(['current_users' => 0]);
            }
            $this->updateGatewayStatus($gateway->fresh());

            Log::info("WaGatewayService: User {$user->id} released dari gateway {$assignment->gateway_id}. Reason: {$reason}");

            return true;
        });
    }

    // ────────────────────────────────────────────────
    // RE-ASSIGN: Pindah user ke gateway lain
    // Kirim notif ke user bahwa nomor bot berubah
    // ────────────────────────────────────────────────
    public function reassignUser(User $user, WaGateway $newGateway): bool
    {
        // Ambil gateway lama sebelum release
        $oldAssignment = UserWaGateway::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('gateway')
            ->first();
        $oldGateway = $oldAssignment?->gateway;

        return DB::transaction(function () use ($user, $newGateway, $oldGateway) {
            $this->releaseGateway($user, 'manual_reassign');

            UserWaGateway::create([
                'user_id' => $user->id,
                'gateway_id' => $newGateway->id,
                'status' => 'active',
                'assigned_at' => now(),
            ]);

            $newGateway->increment('current_users');
            $this->updateGatewayStatus($newGateway->fresh());

            // ③ Notif user bahwa nomor bot berubah
            $this->sendGatewayChangedMessage($user, $oldGateway, $newGateway);

            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'gateway_changed',
                'title' => '📱 Nomor Bot WA Kamu Berubah',
                'body' => "Nomor bot lamamu sudah tidak aktif.\nNomor bot barumu: {$newGateway->phone_number}\nSimpan nomor ini dan hapus kontak lama.",
            ]);

            return true;
        });
    }

    // ────────────────────────────────────────────────
    // ASSIGN AFTER RESUBSCRIBE
    // Dipanggil saat user bayar lagi setelah expired
    // ────────────────────────────────────────────────
    public function assignAfterResubscribe(User $user): ?WaGateway
    {
        // Cek apakah masih punya assignment aktif (mungkin belum direlease)
        $existing = UserWaGateway::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('gateway')
            ->first();

        if ($existing) {
            // Masih punya, kirim pengingat nomor yang sama
            $this->sendBotReminderMessage($user, $existing->gateway);

            return $existing->gateway;
        }

        // Tidak punya, assign baru
        $newGateway = $this->assignGateway($user);

        if ($newGateway) {
            // Cek apakah nomor botnya beda dari yang terakhir
            $lastAssignment = UserWaGateway::where('user_id', $user->id)
                ->where('status', 'released')
                ->orderByDesc('released_at')
                ->with('gateway')
                ->first();

            $lastGateway = $lastAssignment?->gateway;

            if ($lastGateway && $lastGateway->id !== $newGateway->id) {
                // Nomor bot berubah — sudah dikirim di assignGateway via sendWelcomeMessage
                // tapi tambahkan info bahwa nomor lama sudah tidak aktif
                $this->sendViaGateway(
                    $newGateway,
                    $user->wa_number,
                    "ℹ️ *Info Penting*\n\nNomor bot WA kamu sebelumnya (*{$lastGateway->phone_number}*) sudah tidak aktif.\n\nNomor bot barumu adalah nomor yang baru saja mengirim pesan ini.\nSilakan simpan dan hapus kontak lama. 🙏",
                    'system',
                    $user->id
                );
            }
        }

        return $newGateway;
    }

    // ────────────────────────────────────────────────
    // SEND TO USER: Kirim via gateway user
    // ────────────────────────────────────────────────
    public function sendToUser(User $user, string $message, string $type = 'system'): bool
    {
        if (! $user->wa_number) {
            return false;
        }

        $assignment = UserWaGateway::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('gateway')
            ->first();

        if (! $assignment?->gateway) {
            $gateway = $this->assignGateway($user);
            if (! $gateway) {
                return false;
            }
            $assignment = UserWaGateway::where('user_id', $user->id)
                ->where('status', 'active')->with('gateway')->first();
        }

        $gateway = $assignment->gateway;

        if (in_array($gateway->status, ['suspended', 'inactive'])) {
            Log::warning("WaGatewayService: Gateway {$gateway->id} {$gateway->status}. Pesan ke user {$user->id} tidak terkirim.");
            $this->logSend($gateway->id, $user->id, $user->wa_number, $type, 'failed', "Gateway {$gateway->status}");

            return false;
        }

        return $this->sendViaGateway($gateway, $user->wa_number, $message, $type, $user->id);
    }

    // ────────────────────────────────────────────────
    // SEND VIA GATEWAY: HTTP call ke Fonnte
    // ────────────────────────────────────────────────
    public function sendViaGateway(WaGateway $gateway, string $toNumber, string $message, string $type = 'system', ?string $userId = null): bool
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => $gateway->fonnte_token])
                ->post('https://api.fonnte.com/send', [
                    'target' => $toNumber,
                    'message' => $message,
                ]);

            $success = $response->successful() && $response->json('status') === true;

            if ($success) {
                DB::table('wa_gateways')->where('id', $gateway->id)->update([
                    'total_sent_today' => DB::raw('total_sent_today + 1'),
                    'total_sent_all' => DB::raw('total_sent_all + 1'),
                    'last_used_at' => now(),
                ]);
            }

            $this->logSend($gateway->id, $userId, $toNumber, $type,
                $success ? 'sent' : 'failed',
                $success ? null : ($response->json('reason') ?? 'Unknown'));

            return $success;

        } catch (\Exception $e) {
            Log::error("WaGatewayService send error: {$e->getMessage()}");
            $this->logSend($gateway->id, $userId, $toNumber, $type, 'failed', $e->getMessage());

            return false;
        }
    }

    // ────────────────────────────────────────────────
    // WELCOME MESSAGE: Dikirim saat user pertama assign
    // ────────────────────────────────────────────────
    private function sendWelcomeMessage(User $user, WaGateway $gateway): void
    {
        $name = $user->name;
        $botNum = $gateway->phone_number;
        $message = "👋 Halo *{$name}*! Selamat datang di *CatatCuan*!\n\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📱 *Nomor Bot WA Kamu*\n"
            ."*{$botNum}*\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."➡️ *Simpan nomor ini* di kontakmu dengan nama *\"CatatCuan Bot\"*\n\n"
            ."Kamu bisa langsung mencatat keuangan dengan kirim pesan ke nomor ini. Contoh:\n\n"
            ."💸 *\"Makan siang 35rb\"* → catat pengeluaran\n"
            ."💵 *\"Gaji Juni 8 juta\"* → catat pemasukan\n"
            ."💰 *\"Saldo\"* → cek total saldo\n"
            ."📊 *\"Laporan bulan ini\"* → ringkasan bulan ini\n"
            ."🧾 *\"Tagihan\"* → lihat tagihan aktif\n"
            ."❓ *\"Bantuan\"* → semua command\n\n"
            .'_CatatCuan — Catat keuangan, hidup lebih tenang_ ✨';

        $this->sendViaGateway($gateway, $user->wa_number, $message, 'system', $user->id);
    }

    // ────────────────────────────────────────────────
    // GATEWAY CHANGED MESSAGE: Dikirim saat nomor bot berubah
    // ────────────────────────────────────────────────
    private function sendGatewayChangedMessage(User $user, ?WaGateway $oldGateway, WaGateway $newGateway): void
    {
        $name = $user->name;
        $message = "🔄 *Halo {$name}, Ada Info Penting!*\n\n"
            ."Nomor bot CatatCuan kamu telah berubah.\n\n"
            .($oldGateway ? "❌ *Nomor lama (hapus dari kontak):*\n{$oldGateway->phone_number}\n\n" : '')
            ."✅ *Nomor baru (simpan di kontak):*\n*{$newGateway->phone_number}*\n\n"
            ."Mulai sekarang, kirim semua pesan ke nomor baru ini ya! 🙏\n\n"
            .'_Maaf atas ketidaknyamanannya — CatatCuan_';

        $this->sendViaGateway($newGateway, $user->wa_number, $message, 'system', $user->id);
    }

    // ────────────────────────────────────────────────
    // BOT REMINDER: Ingatkan nomor bot (saat subscribe ulang, bot sama)
    // ────────────────────────────────────────────────
    private function sendBotReminderMessage(User $user, WaGateway $gateway): void
    {
        $message = "✅ *Langganan CatatCuan aktif kembali!*\n\n"
            ."Nomor bot kamu masih sama:\n*{$gateway->phone_number}*\n\n"
            .'Yuk lanjut catat keuanganmu! 💪';

        $this->sendViaGateway($gateway, $user->wa_number, $message, 'system', $user->id);
    }

    // ────────────────────────────────────────────────
    // NOTIFY ADMIN: Tidak ada slot tersedia
    // ────────────────────────────────────────────────
    private function notifyAdminNoSlot(): void
    {
        // Kirim notif ke semua super admin
        $admins = User::where('role', 'super_admin')
            ->whereNotNull('wa_number')
            ->get();

        foreach ($admins as $admin) {
            $gateway = WaGateway::where('status', 'active')->first();
            if (! $gateway) {
                continue;
            }

            $this->sendViaGateway(
                $gateway,
                $admin->wa_number,
                "⚠️ *Alert CatatCuan Admin*\n\nSemua WA Gateway sudah penuh!\nUser baru tidak bisa di-assign nomor bot.\n\nSegera tambah nomor baru di Admin Panel:\nadm.catatcuan.com/wa-gateway",
                'system'
            );
        }
    }

    // ────────────────────────────────────────────────
    // RELEASE INACTIVE: Scan user tidak aktif
    // ────────────────────────────────────────────────
    public function releaseInactiveUsers(): array
    {
        $released = [];

        // 1. User tanpa nomor WA
        $noWa = User::whereNull('wa_number')
            ->whereHas('waGatewayAssignment')
            ->get();
        foreach ($noWa as $user) {
            if ($this->releaseGateway($user, 'no_wa_number')) {
                $released[] = ['user_id' => $user->id, 'reason' => 'no_wa_number'];
            }
        }

        // 2. Subscription expired > 30 hari
        $expired = User::whereHas('subscription', fn ($q) => $q->where('status', 'expired')->where('ends_at', '<', now()->subDays(30))
        )->whereHas('waGatewayAssignment')->get();
        foreach ($expired as $user) {
            if ($this->releaseGateway($user, 'subscription_expired')) {
                $released[] = ['user_id' => $user->id, 'reason' => 'subscription_expired'];
            }
        }

        // 3. Tidak ada transaksi > 60 hari
        $inactive = User::where('role', 'user')
            ->whereDoesntHave('transactions', fn ($q) => $q->where('created_at', '>=', now()->subDays(60))
            )->whereHas('waGatewayAssignment')->get();
        foreach ($inactive as $user) {
            if ($this->releaseGateway($user, 'user_inactive')) {
                $released[] = ['user_id' => $user->id, 'reason' => 'user_inactive'];
            }
        }

        Log::info('WaGatewayService: Released '.count($released).' slots.');

        return $released;
    }

    // ────────────────────────────────────────────────
    // TEST GATEWAY
    // ────────────────────────────────────────────────
    public function testGateway(WaGateway $gateway, string $toNumber): array
    {
        $message = "✅ *Test CatatCuan Gateway*\n\nNomor *{$gateway->name}* ({$gateway->phone_number}) berhasil terhubung!\n\nWaktu: ".now('Asia/Jakarta')->format('d M Y, H:i:s WIB');
        $success = $this->sendViaGateway($gateway, $toNumber, $message, 'test');

        return [
            'success' => $success,
            'message' => $success ? 'Pesan test berhasil dikirim!' : 'Gagal mengirim pesan test.',
        ];
    }

    // ────────────────────────────────────────────────
    // RESET DAILY COUNTER
    // ────────────────────────────────────────────────
    public function resetDailyCounters(): void
    {
        DB::table('wa_gateways')->update([
            'total_sent_today' => 0,
            'last_reset_at' => now(),
        ]);
    }

    // ────────────────────────────────────────────────
    // RECALCULATE COUNTERS
    // ────────────────────────────────────────────────
    public function recalculateAllCounters(): void
    {
        WaGateway::all()->each(function ($g) {
            $actual = UserWaGateway::where('gateway_id', $g->id)->where('status', 'active')->count();
            $g->update(['current_users' => $actual]);
            $this->updateGatewayStatus($g->fresh());
        });
    }

    // ────────────────────────────────────────────────
    // GET STATS
    // ────────────────────────────────────────────────
    public function getGatewayStats(): array
    {
        return WaGateway::orderBy('sort_order')->get()->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'phone_number' => $g->phone_number,
            'status' => $g->status,
            'status_color' => $g->status_color,
            'status_note' => $g->status_note,
            'current_users' => $g->current_users,
            'max_users' => $g->max_users,
            'slot_available' => $g->slot_available,
            'usage_percent' => $g->usage_percent,
            'total_sent_today' => $g->total_sent_today,
            'total_sent_all' => $g->total_sent_all,
            'last_used_at' => $g->last_used_at?->diffForHumans(),
            'is_default' => $g->is_default,
        ])->toArray();
    }

    // ────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ────────────────────────────────────────────────
    private function updateGatewayStatus(WaGateway $gateway): void
    {
        if (in_array($gateway->status, ['suspended', 'inactive'])) {
            return;
        }

        $pct = $gateway->max_users > 0
            ? ($gateway->current_users / $gateway->max_users) * 100 : 0;

        $new = $pct >= 90 ? 'warning' : 'active';
        $note = $new === 'warning'
            ? "Kapasitas {$gateway->current_users}/{$gateway->max_users} ({$pct}%). Segera tambah nomor baru."
            : null;

        if ($gateway->status !== $new) {
            $gateway->update(['status' => $new, 'status_note' => $note]);
        }
    }

    private function logSend(int $gatewayId, ?string $userId, string $to, string $type, string $status, ?string $error = null): void
    {
        try {
            WaGatewayLog::create([
                'gateway_id' => $gatewayId,
                'user_id' => $userId,
                'to_number' => $to,
                'type' => $type,
                'status' => $status,
                'error_message' => $error,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("WaGatewayService log error: {$e->getMessage()}");
        }
    }
}
