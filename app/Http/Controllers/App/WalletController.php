<?php

namespace App\Http\Controllers\App;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
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
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'bank_id' => ['nullable', 'exists:banks,id'],
            'display_name' => ['required', 'string', 'max:60'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'type' => ['required', 'in:cash_flow,saving,both,investment'],
            'is_saham' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'string', 'in:primary,success,danger,warning,info'],
        ]);

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
            'sort_order' => $lastOrder + 1,
            'icon' => $request->icon,
            'color' => $request->color,
        ]);

        return back()->with('success', "Dompet {$wallet->display_name} berhasil ditambahkan!");
    }

    // ─────────────────────────────────────────────
    // Update dompet yang sudah ada
    // ─────────────────────────────────────────────
    public function update(Request $request, UserWallet $wallet): RedirectResponse
    {
        abort_if($wallet->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        $request->validate([
            'display_name' => ['required', 'string', 'max:60'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:cash_flow,saving,both,investment'],
            'is_active' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'string', 'in:primary,success,danger,warning,info'],
        ]);

        $wallet->update([
            'display_name' => $request->display_name,
            'account_number' => $request->account_number,
            'type' => $request->type,
            'is_active' => $request->boolean('is_active', true),
            'icon' => $request->icon,
            'color' => $request->color,
        ]);

        return back()->with('success', "Dompet {$wallet->display_name} berhasil diupdate!");
    }

    // ─────────────────────────────────────────────
    // Aksi cepat: arsip / aktifkan dompet dari kartu dompet
    // ─────────────────────────────────────────────
    public function archive(Request $request, UserWallet $wallet): RedirectResponse
    {
        abort_if($wallet->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        $wallet->update(['is_active' => ! $wallet->is_active]);

        $message = $wallet->is_active
            ? "Dompet {$wallet->display_name} berhasil diaktifkan!"
            : "Dompet {$wallet->display_name} berhasil diarsipkan!";

        return back()->with('success', $message);
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
    // ─────────────────────────────────────────────
    public function transfer(Request $request): RedirectResponse
    {
        $request->validate([
            'from_wallet_id' => ['required', 'exists:user_wallets,id', 'different:to_wallet_id'],
            'to_wallet_id' => ['required', 'exists:user_wallets,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'transferred_at' => ['required', 'date'],
        ]);

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
                $transferId
            );
        });

        return back()->with(
            'success',
            'Berhasil transfer Rp '.number_format($request->amount, 0, ',', '.')." dari {$fromWallet->display_name} ke {$toWallet->display_name}!"
        );
    }

    // ─────────────────────────────────────────────
    // Hapus/batalkan transfer — reversal penuh saldo kedua dompet
    // ─────────────────────────────────────────────
    public function destroyTransfer(Request $request, WalletTransfer $walletTransfer): RedirectResponse
    {
        abort_if($walletTransfer->user_id !== $request->user()->id, 403, 'Akses ditolak.');

        try {
            DB::transaction(function () use ($walletTransfer) {
                $this->walletService->reverseTransfer($walletTransfer);
                $walletTransfer->delete();
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer berhasil dibatalkan, saldo dikembalikan.');
    }
}
