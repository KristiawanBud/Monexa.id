<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserWaGateway;
use App\Models\WaGateway;
use App\Models\WaGatewayLog;
use App\Services\WaGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WaGatewayController extends Controller
{
    public function __construct(private WaGatewayService $gatewayService) {}

    // ─────────────────────────────────────────────────────────────
    // Halaman utama daftar gateway
    //
    // CSS FIX: status_color dan status_css_class sekarang
    // menggunakan kelas utilitas monokromatik dari app.css:
    //   .text-green  → --green-dark  (active)
    //   .text-amber  → --amber       (warning)
    //   .text-red    → --red-dark    (suspended / disconnected)
    //   .text-muted  → --ink-muted   (inactive)
    // ─────────────────────────────────────────────────────────────
    public function index(): Response
    {
        $gateways = WaGateway::orderBy('sort_order')->get()->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'phone_number' => $g->phone_number,
            'status' => $g->status,

            // ── FIX: Class CSS sinkron dengan design system app.css ──
            'status_css_class' => $g->status_css_class,
            'status_label' => $this->statusLabel($g->status),

            'status_note' => $g->status_note,
            'current_users' => $g->current_users,
            'max_users' => $g->max_users,
            'slot_available' => $g->slot_available,
            'usage_percent' => $g->usage_percent,
            'total_sent_today' => $g->total_sent_today,
            'total_sent_all' => $g->total_sent_all,
            'last_used_at' => $g->last_used_at?->diffForHumans(),
            'is_default' => $g->is_default,
            'sort_order' => $g->sort_order,
            'owner_wa_number' => $g->owner_wa_number,

            // ── Monitoring status koneksi ──────────────────────────
            'is_connected' => $g->is_connected,
            'is_possibly_disconnected' => $g->is_possibly_disconnected,
            'last_ping_at' => $g->last_ping_at?->diffForHumans(),
            'last_ping_at_raw' => $g->last_ping_at?->toISOString(),
            'minutes_since_ping' => $g->minutes_since_last_ping,
            'disconnected_at' => $g->disconnected_at?->diffForHumans(),

            // ── Class CSS monitoring ───────────────────────────────
            'connection_css_class' => ($g->is_connected && ! $g->is_possibly_disconnected)
                ? 'text-green'
                : 'text-red',
            'connection_label' => $this->connectionLabel($g),
        ]);

        // Summary stats
        $totalCapacity = WaGateway::sum('max_users');
        $totalAssigned = WaGateway::sum('current_users');
        $activeGateways = WaGateway::where('status', 'active')->count();
        $warnGateways = WaGateway::where('status', 'warning')->count();
        $disconnected = WaGateway::where('is_connected', false)->count();

        // Log terbaru 50 entri
        $recentLogs = WaGatewayLog::with(['gateway:id,name', 'user:id,name'])
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'gateway_name' => $l->gateway?->name,
                'user_name' => $l->user?->name,
                'to_number' => substr($l->to_number, 0, 6).'****',
                'type' => $l->type,
                'status' => $l->status,
                'status_class' => $l->status === 'sent' ? 'text-green' : 'text-red',
                'error' => $l->error_message,
                'sent_at' => $l->sent_at?->format('d M, H:i'),
            ]);

        // Owner WA number dari system_settings (global)
        $ownerWaNumber = DB::table('system_settings')
            ->where('key', 'owner_wa_number')
            ->value('value');

        return Inertia::render('Admin/WaGateway', compact(
            'gateways', 'totalCapacity', 'totalAssigned',
            'activeGateways', 'warnGateways', 'disconnected',
            'recentLogs', 'ownerWaNumber'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // Tambah gateway baru
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone_number' => ['required', 'string', 'unique:wa_gateways,phone_number'],
            'fonnte_token' => ['required', 'string'],
            'max_users' => ['required', 'integer', 'min:1', 'max:500'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer'],
            'owner_wa_number' => ['nullable', 'string', 'max:25'],
        ]);

        if ($request->boolean('is_default')) {
            WaGateway::where('is_default', true)->update(['is_default' => false]);
        }

        WaGateway::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'fonnte_token' => $request->fonnte_token,
            'max_users' => $request->max_users,
            'status' => 'active',
            'is_connected' => true,
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $request->input('sort_order', 99),
            'owner_wa_number' => $request->owner_wa_number,
        ]);

        return back()->with('success', "Gateway {$request->name} berhasil ditambahkan!");
    }

    // ─────────────────────────────────────────────────────────────
    // Update gateway
    // ─────────────────────────────────────────────────────────────
    public function update(Request $request, WaGateway $gateway): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'max_users' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,warning,suspended,inactive'],
            'status_note' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer'],
            'owner_wa_number' => ['nullable', 'string', 'max:25'],
        ]);

        if ($request->boolean('is_default') && ! $gateway->is_default) {
            WaGateway::where('is_default', true)->update(['is_default' => false]);
        }

        $gateway->update($request->only(
            'name', 'max_users', 'status', 'status_note',
            'is_default', 'sort_order', 'owner_wa_number'
        ));

        return back()->with('success', "Gateway {$gateway->name} berhasil diupdate!");
    }

    // ─────────────────────────────────────────────────────────────
    // Hapus gateway (hanya jika tidak ada user aktif)
    // ─────────────────────────────────────────────────────────────
    public function destroy(WaGateway $gateway): RedirectResponse
    {
        if ($gateway->current_users > 0) {
            return back()->with(
                'error',
                "Tidak bisa hapus gateway yang masih memiliki {$gateway->current_users} user aktif."
            );
        }

        $gateway->delete();

        return back()->with('success', 'Gateway berhasil dihapus.');
    }

    // ─────────────────────────────────────────────────────────────
    // Test kirim pesan ke nomor tertentu
    // Sekaligus update last_ping_at dan is_connected
    // ─────────────────────────────────────────────────────────────
    public function test(Request $request, WaGateway $gateway): RedirectResponse
    {
        $request->validate([
            'to_number' => ['required', 'string', 'max:25'],
        ]);

        $result = $this->gatewayService->testGateway($gateway, $request->to_number);

        // Update status koneksi berdasarkan hasil test
        $gateway->update([
            'last_ping_at' => now(),
            'is_connected' => $result['success'],
        ]);

        if (! $result['success']) {
            // Catat waktu disconnect pertama jika belum tercatat
            if ($gateway->is_connected) {
                $gateway->update(['disconnected_at' => now()]);
            }

            // Kirim alert ke owner
            $this->sendDisconnectAlert($gateway, 'Test gagal: '.($result['message'] ?? 'Unknown'));
        } else {
            // Koneksi berhasil — reset disconnected_at
            $gateway->update(['disconnected_at' => null]);
        }

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Ping otomatis — dipanggil dari scheduler setiap 15 menit
    // ─────────────────────────────────────────────────────────────
    public function pingAll(): array
    {
        $gateways = WaGateway::whereIn('status', ['active', 'warning'])->get();
        $results = [];

        foreach ($gateways as $gateway) {
            // Cek status via Fonnte API (cek device status)
            $connected = $this->gatewayService->checkConnection($gateway);

            $wasConnected = $gateway->is_connected;

            $gateway->update([
                'last_ping_at' => now(),
                'is_connected' => $connected,
            ]);

            if (! $connected && $wasConnected) {
                // Baru disconnect — catat waktu dan kirim alert
                $gateway->update(['disconnected_at' => now()]);
                $this->sendDisconnectAlert($gateway);
            } elseif ($connected && ! $wasConnected) {
                // Baru reconnect — reset
                $gateway->update(['disconnected_at' => null]);
                $this->sendReconnectAlert($gateway);
            }

            $results[] = [
                'gateway' => $gateway->name,
                'connected' => $connected,
            ];
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────
    // Lihat user yang terhubung ke gateway ini
    // ─────────────────────────────────────────────────────────────
    public function users(Request $request, WaGateway $gateway): Response
    {
        $users = UserWaGateway::where('gateway_id', $gateway->id)
            ->where('status', 'active')
            ->with('user:id,name,email,wa_number')
            ->orderByDesc('assigned_at')
            ->paginate(30)
            ->through(fn ($a) => [
                'id' => $a->user_id,
                'name' => $a->user?->name,
                'email' => $a->user?->email,
                'wa_number' => $a->user?->wa_number,
                'assigned_at' => $a->assigned_at?->format('d M Y'),
            ]);

        return Inertia::render('Admin/WaGatewayUsers', [
            'gateway' => [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'phone_number' => $gateway->phone_number,
                'status' => $gateway->status,
                'status_class' => $gateway->status_css_class,
            ],
            'users' => $users,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Reassign user ke gateway lain
    // ─────────────────────────────────────────────────────────────
    public function reassign(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'gateway_id' => ['required', 'exists:wa_gateways,id'],
        ]);

        $user = User::findOrFail($request->user_id);
        $newGateway = WaGateway::findOrFail($request->gateway_id);

        if ($newGateway->getIsFull()) {
            return back()->with(
                'error',
                "Gateway {$newGateway->name} sudah penuh ({$newGateway->current_users}/{$newGateway->max_users})."
            );
        }

        $this->gatewayService->reassignUser($user, $newGateway);

        return back()->with('success', "{$user->name} berhasil dipindahkan ke {$newGateway->name}.");
    }

    // ─────────────────────────────────────────────────────────────
    // Release user tidak aktif secara manual
    // ─────────────────────────────────────────────────────────────
    public function releaseInactive(): RedirectResponse
    {
        $released = $this->gatewayService->releaseInactiveUsers();

        return back()->with('success', count($released).' slot berhasil dibebaskan.');
    }

    // ─────────────────────────────────────────────────────────────
    // Sinkronisasi counter (recalculate dari data aktual)
    // ─────────────────────────────────────────────────────────────
    public function recalculate(): RedirectResponse
    {
        $this->gatewayService->recalculateAllCounters();

        return back()->with('success', 'Counter semua gateway berhasil di-sinkronisasi.');
    }

    // ─────────────────────────────────────────────────────────────
    // Simpan nomor WA pribadi owner (global, semua gateway)
    // ─────────────────────────────────────────────────────────────
    public function saveOwnerNumber(Request $request): RedirectResponse
    {
        $request->validate([
            'owner_wa_number' => ['required', 'string', 'max:25', 'regex:/^[0-9]+$/'],
        ]);

        DB::table('system_settings')
            ->updateOrInsert(
                ['key' => 'owner_wa_number'],
                ['value' => $request->owner_wa_number, 'updated_at' => now()]
            );

        // Update juga ke semua gateway yang owner_wa_number-nya masih null
        WaGateway::whereNull('owner_wa_number')
            ->update(['owner_wa_number' => $request->owner_wa_number]);

        return back()->with('success', 'Nomor WA pribadi owner berhasil disimpan!');
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Kirim alert disconnect ke WA pribadi owner
    // ─────────────────────────────────────────────────────────────
    private function sendDisconnectAlert(WaGateway $gateway, string $reason = ''): void
    {
        $ownerNumber = $this->resolveOwnerNumber($gateway);
        if (! $ownerNumber) {
            return;
        }

        // Cari gateway lain yang masih aktif untuk kirim notif
        $activeGateway = WaGateway::where('status', 'active')
            ->where('is_connected', true)
            ->where('id', '!=', $gateway->id)
            ->first();

        if (! $activeGateway) {
            return;
        } // Tidak ada gateway aktif lain untuk kirim

        $message = "🚨 *Alert CatatCuan — WA Bot Disconnect*\n\n"
            ."Gateway *{$gateway->name}* ({$gateway->phone_number}) "
            ."terdeteksi terputus dari Fonnte!\n\n"
            .($reason ? "❗ Alasan: {$reason}\n\n" : '')
            .'⏰ Waktu: '.now('Asia/Jakarta')->format('d M Y, H:i')." WIB\n\n"
            ."Silakan cek dashboard:\n"
            .url('/admin/wa-gateway')."\n\n"
            .'_Alert otomatis dari CatatCuan Monitoring_';

        $this->gatewayService->sendViaGateway(
            $activeGateway,
            $ownerNumber,
            $message,
            'system'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Kirim notif reconnect ke WA pribadi owner
    // ─────────────────────────────────────────────────────────────
    private function sendReconnectAlert(WaGateway $gateway): void
    {
        $ownerNumber = $this->resolveOwnerNumber($gateway);
        if (! $ownerNumber) {
            return;
        }

        $message = "✅ *CatatCuan — WA Bot Terhubung Kembali*\n\n"
            ."Gateway *{$gateway->name}* ({$gateway->phone_number}) "
            ."sudah terhubung kembali ke Fonnte.\n\n"
            .'⏰ Waktu: '.now('Asia/Jakarta')->format('d M Y, H:i')." WIB\n\n"
            .'_Alert otomatis dari CatatCuan Monitoring_';

        $activeGateway = WaGateway::where('status', 'active')
            ->where('is_connected', true)
            ->first();

        if ($activeGateway) {
            $this->gatewayService->sendViaGateway(
                $activeGateway,
                $ownerNumber,
                $message,
                'system'
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Resolve nomor WA owner dari gateway atau system_settings
    // ─────────────────────────────────────────────────────────────
    private function resolveOwnerNumber(WaGateway $gateway): ?string
    {
        // Prioritas 1: owner_wa_number di gateway itu sendiri
        if ($gateway->owner_wa_number) {
            return $gateway->owner_wa_number;
        }

        // Prioritas 2: setting global di system_settings
        $global = DB::table('system_settings')
            ->where('key', 'owner_wa_number')
            ->value('value');

        return $global ?: null;
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Label status gateway
    // ─────────────────────────────────────────────────────────────
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => '✅ Aktif',
            'warning' => '⚠️ Warning',
            'suspended' => '🚫 Suspend',
            'inactive' => '⏸️ Nonaktif',
            default => $status,
        };
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Label koneksi gateway
    // ─────────────────────────────────────────────────────────────
    private function connectionLabel(WaGateway $gateway): string
    {
        if (! $gateway->is_connected) {
            return '🔴 Disconnect';
        }
        if ($gateway->is_possibly_disconnected) {
            $mins = $gateway->minutes_since_last_ping;

            return "🟡 Tidak ada sinyal ({$mins} menit)";
        }

        return '🟢 Terhubung';
    }
}
