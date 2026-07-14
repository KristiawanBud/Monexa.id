<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\UpdateThemeRequest;
use App\Models\UserProfile;
use App\Services\WaGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private WaGatewayService $gatewayService) {}

    // ─────────────────────────────────────────────
    // Halaman Profil — tanpa kelola dompet (sudah pindah ke Dompet)
    // ─────────────────────────────────────────────
    public function index(Request $request): Response
    {
        $user = $request->user()->load(['profile', 'subscription', 'waGatewayAssignment.gateway']);

        $activeGateway = $user->waGatewayAssignment?->gateway;

        return Inertia::render('App/Account', [
            'user' => $user->only('id', 'name', 'email', 'wa_number', 'role'),
            'profile' => $user->profile,
            'subscription' => $user->subscription,
            'bot_gateway' => $activeGateway ? [
                'phone_number' => $activeGateway->phone_number,
                'name' => $activeGateway->name,
                'status' => $activeGateway->status,
                'assigned_at' => $user->waGatewayAssignment->assigned_at->format('d M Y'),
            ] : null,
        ]);
    }

    // ─────────────────────────────────────────────
    // FIX BUG #4: Update profil — pastikan saham_enabled benar2 tersimpan
    //
    // Masalah sebelumnya: form mengirim boolean tapi bisa jadi browser
    // tidak mengirim field checkbox sama sekali saat unchecked,
    // sehingga $request->boolean() fallback ke default 'false' terus.
    // Fix: pastikan request selalu mengirim eksplisit true/false dari
    // frontend (lihat Account.vue), dan validasi di sini eksplisit boolean.
    // ─────────────────────────────────────────────
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'wa_number' => ['required', 'string', 'max:20'],
            'notif_wa_enabled' => ['boolean'],
            'monthly_report_enabled' => ['boolean'],
            'saham_enabled' => ['boolean'],
        ]);

        $user = $request->user();
        $oldWaNumber = $user->wa_number;

        $user->update([
            'name' => $request->name,
            'wa_number' => $request->wa_number,
        ]);

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'notif_wa_enabled' => $request->boolean('notif_wa_enabled'),
                'monthly_report_enabled' => $request->boolean('monthly_report_enabled'),
                'saham_enabled' => $request->boolean('saham_enabled'),
            ]
        );

        if (! $oldWaNumber && $request->wa_number) {
            $this->gatewayService->assignGateway($user->fresh());
        }

        return back()->with('success', 'Profil berhasil diupdate!');
    }

    // ─────────────────────────────────────────────
    // Update preferensi tema (persist per-user, bukan cuma localStorage)
    // ─────────────────────────────────────────────
    public function updateTheme(UpdateThemeRequest $request)
    {
        $request->user()->profile->update(['theme' => $request->theme]);

        return back()->with('success', 'Tema berhasil disimpan.');
    }

    // ─────────────────────────────────────────────
    // Ganti password
    // ─────────────────────────────────────────────
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password berhasil diubah!');
    }

    // ─────────────────────────────────────────────
    // FITUR BARU: Reset Data — verifikasi password sebelum hapus
    //
    // Menghapus semua data transaksional (transactions, wallets, bills,
    // saving_goals, budgets, assets) milik user TANPA menghapus akun
    // itu sendiri. Berguna untuk membersihkan data hasil coba-coba
    // saat testing tanpa harus daftar ulang dari nol.
    // ─────────────────────────────────────────────
    public function resetData(Request $request)
    {
        $request->validate([
            'password' => ['required'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Password salah. Reset data dibatalkan.']);
        }

        DB::transaction(function () use ($user) {
            // Hapus semua data transaksional
            $user->transactions()->delete();
            $user->savingGoals()->each(function ($goal) {
                $goal->deposits()->delete();
                $goal->delete();
            });
            $user->bills()->each(function ($bill) {
                $bill->payments()->delete();
                $bill->delete();
            });
            $user->budgets()->delete();
            $user->assets()->delete();
            $user->wallets()->delete();

            // Hapus log terkait
            DB::table('wallet_balance_logs')
                ->whereIn('wallet_id', function ($q) use ($user) {
                    $q->select('id')->from('user_wallets')->where('user_id', $user->id);
                })->delete();
        });

        return back()->with('success', 'Semua data percobaan berhasil direset! Akun kamu tetap aman.');
    }
}
