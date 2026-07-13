<?php

namespace App\Http\Controllers\App;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\StoreWalletRequest;
use App\Http\Requests\App\TransferWalletRequest;
use App\Http\Requests\App\UpdateWalletRequest;
use App\Models\UserWallet;
use App\Models\WalletTransfer;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    // ─────────────────────────────────────────────
    // Tambah dompet baru
    // ─────────────────────────────────────────────
    public function store(StoreWalletRequest $request): RedirectResponse
    {
        $user = $request->user();
        $lastOrder = $user->wallets()->max('sort_order') ?? 0;

        $wallet = $user->wallets()->create([
            'bank_id' => $request->bank_id,
            'display_name' => $request->display_name,
            'account_number' => $request->account_number,
            'balance' => $request->initial_balance ?? 0,
            'initial_balance' => $request->initial_balance ?? 0,
            'type' => $request->type,
            'is_saham' => $request->boolean('is_saham', false),
            'is_active' => true,
            'currency' => $request->currency ?? 'IDR',
            'sort_order' => $lastOrder + 1,
        ]);

        return back()->with('success', "Dompet {$wallet->display_name} berhasil ditambahkan!");
    }

    // ─────────────────────────────────────────────
    // Update dompet yang sudah ada
    // ─────────────────────────────────────────────
    public function update(UpdateWalletRequest $request, UserWallet $wallet): RedirectResponse
    {
        abort_if($wallet->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        $wallet->update([
            'display_name' => $request->display_name,
            'account_number' => $request->account_number,
            'type' => $request->type,
            'is_active' => $request->boolean('is_active', true),
            'currency' => $request->currency ?? $wallet->currency,
        ]);

        return back()->with('success', "Dompet {$wallet->display_name} berhasil diupdate!");
    }

    // ─────────────────────────────────────────────
    // Jadikan dompet utama (maksimal 1 is_primary=true per user)
    // ─────────────────────────────────────────────
    public function setPrimary(Request $request, UserWallet $wallet): RedirectResponse
    {
        abort_if($wallet->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        if (! $wallet->is_active) {
            return back()->with('error', 'Tidak bisa menjadikan dompet yang diarsipkan sebagai dompet utama.');
        }

        DB::transaction(function () use ($request, $wallet) {
            UserWallet::where('user_id', $request->user()->id)
                ->where('id', '!=', $wallet->id)
                ->update(['is_primary' => false]);

            $wallet->update(['is_primary' => true]);
        });

        return back()->with('success', "{$wallet->display_name} dijadikan dompet utama.");
    }

    // ─────────────────────────────────────────────
    // Arsipkan dompet (is_active=false, tanpa soft delete)
    // ─────────────────────────────────────────────
    public function archive(Request $request, UserWallet $wallet): RedirectResponse
    {
        abort_if($wallet->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        $user = $request->user();

        if ($user->wallets()->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Tidak bisa mengarsipkan dompet terakhir.');
        }

        DB::transaction(function () use ($user, $wallet) {
            $wasPrimary = $wallet->is_primary;

            $wallet->update(['is_active' => false, 'is_primary' => false]);

            if ($wasPrimary) {
                $nextPrimary = $user->wallets()
                    ->where('is_active', true)
                    ->where('id', '!=', $wallet->id)
                    ->orderBy('sort_order')
                    ->first();

                $nextPrimary?->update(['is_primary' => true]);
            }
        });

        return back()->with('success', "Dompet {$wallet->display_name} berhasil diarsipkan.");
    }

    // ─────────────────────────────────────────────
    // Pulihkan dompet yang diarsipkan (tidak otomatis jadi primary)
    // ─────────────────────────────────────────────
    public function restore(Request $request, UserWallet $wallet): RedirectResponse
    {
        abort_if($wallet->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        $wallet->update(['is_active' => true]);

        return back()->with('success', "Dompet {$wallet->display_name} berhasil dipulihkan.");
    }

    // ─────────────────────────────────────────────
    // PRIORITAS #2: Hapus dompet
    //
    // Aturan: dompet hanya bisa dihapus jika saldo = 0 dan tidak
    // sedang dipakai sebagai sumber goal tabungan aktif. Soft delete
    // dipakai supaya riwayat transaksi lama tetap utuh & auditable.
    // ─────────────────────────────────────────────
    public function destroy(Request $request, UserWallet $wallet): RedirectResponse
    {
        abort_if($wallet->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        if ((float) $wallet->balance != 0) {
            return back()->with(
                'error',
                'Tidak bisa hapus dompet dengan saldo Rp '.number_format($wallet->balance, 0, ',', '.').'. Pindahkan dulu saldonya via Transfer.'
            );
        }

        $hasTransactions = $wallet->transactions()->exists();

        if ($hasTransactions) {
            // Jangan hard delete kalau ada riwayat transaksi — soft delete saja
            // dan nonaktifkan supaya tidak muncul lagi di pilihan dompet
            $wallet->update(['is_active' => false]);
            $wallet->delete();
        } else {
            // Belum pernah dipakai sama sekali — aman dihapus permanen
            $wallet->forceDelete();
        }

        return back()->with('success', "Dompet {$wallet->display_name} berhasil dihapus.");
    }

    // ─────────────────────────────────────────────
    // PRIORITAS #2: Transfer saldo antar dompet
    // Dicatat sebagai 2 baris Transaction (expense/income) yang terhubung via
    // transfer_id — lihat WalletService::transferBetweenWallets().
    // ─────────────────────────────────────────────
    public function transfer(TransferWalletRequest $request): RedirectResponse
    {
        $user = $request->user();

        $fromWallet = UserWallet::findOrFail($request->from_wallet_id);
        $toWallet = UserWallet::findOrFail($request->to_wallet_id);

        abort_if($fromWallet->user_id !== $user->id, 403);
        abort_if($toWallet->user_id !== $user->id, 403);

        if ((float) $fromWallet->balance < (float) $request->amount) {
            return back()->with(
                'error',
                "Saldo {$fromWallet->display_name} tidak cukup. Saldo saat ini: Rp ".number_format($fromWallet->balance, 0, ',', '.')
            );
        }

        try {
            DB::transaction(function () use ($request, $user, $fromWallet, $toWallet) {
                $transferId = (string) Str::ulid();

                WalletTransfer::create([
                    'id' => $transferId,
                    'user_id' => $user->id,
                    'from_wallet_id' => $fromWallet->id,
                    'to_wallet_id' => $toWallet->id,
                    'amount' => $request->amount,
                    'note' => $request->note,
                    'transferred_at' => $request->transferred_at,
                ]);

                $this->walletService->transferBetweenWallets(
                    $fromWallet,
                    $toWallet,
                    (float) $request->amount,
                    $transferId,
                    $request->transferred_at,
                    $request->note
                );
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Berhasil transfer Rp '.number_format($request->amount, 0, ',', '.')." dari {$fromWallet->display_name} ke {$toWallet->display_name}!"
        );
    }
}
