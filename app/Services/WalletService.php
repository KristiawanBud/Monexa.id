<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\SavingDeposit;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Models\WalletTransfer;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Terapkan transaksi ke saldo dompet + catat balance log.
     * Menolak transaksi (throw exception) kalau pengeluaran bikin saldo minus.
     */
    public function applyTransaction(Transaction $transaction): void
    {
        $wallet = $transaction->wallet;
        $balanceBefore = (float) $wallet->balance;

        if ($transaction->type === 'expense' && $balanceBefore < (float) $transaction->amount) {
            throw new InsufficientBalanceException(
                "Saldo {$wallet->display_name} tidak cukup. Saldo saat ini Rp "
                .number_format($balanceBefore, 0, ',', '.')
                .', transaksi ini butuh Rp '
                .number_format($transaction->amount, 0, ',', '.').'.'
            );
        }

        DB::table('wallet_balance_logs')->insert([
            'wallet_id' => $wallet->id,
            'type' => $transaction->type === 'income' ? 'credit' : 'debit',
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore + ($transaction->type === 'income' ? $transaction->amount : -$transaction->amount),
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
            'created_at' => now(),
        ]);

        if ($transaction->type === 'income') {
            $wallet->increment('balance', $transaction->amount);
        } else {
            $wallet->decrement('balance', $transaction->amount);
        }
    }

    /**
     * Reverse transaksi (saat dihapus/diedit)
     */
    public function reverseTransaction(Transaction $transaction): void
    {
        $wallet = $transaction->wallet;

        if ($transaction->type === 'income') {
            $wallet->decrement('balance', $transaction->amount);
        } else {
            $wallet->increment('balance', $transaction->amount);
        }
    }

    /**
     * Setor ke tabungan: kurangi saldo dompet asal
     */
    public function depositToSaving(UserWallet $wallet, float $amount, SavingDeposit $deposit): void
    {
        $balanceBefore = (float) $wallet->balance;

        if ($balanceBefore < $amount) {
            throw new InsufficientBalanceException(
                "Saldo {$wallet->display_name} tidak cukup untuk setoran ini. Saldo saat ini Rp "
                .number_format($balanceBefore, 0, ',', '.').'.'
            );
        }

        DB::table('wallet_balance_logs')->insert([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore - $amount,
            'reference_type' => 'saving_deposit',
            'reference_id' => $deposit->id,
            'created_at' => now(),
        ]);

        $wallet->decrement('balance', $amount);
    }

    /**
     * Transfer saldo antar dompet milik user yang sama.
     */
    public function transferBetweenWallets(
        UserWallet $fromWallet,
        UserWallet $toWallet,
        float $amount,
        string $transferId
    ): void {
        $fromBefore = (float) $fromWallet->balance;
        $toBefore = (float) $toWallet->balance;

        if ($fromBefore < $amount) {
            throw new InsufficientBalanceException(
                "Saldo {$fromWallet->display_name} tidak cukup untuk transfer ini."
            );
        }

        DB::table('wallet_balance_logs')->insert([
            [
                'wallet_id' => $fromWallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $fromBefore,
                'balance_after' => $fromBefore - $amount,
                'reference_type' => 'wallet_transfer',
                'reference_id' => $transferId,
                'created_at' => now(),
            ],
            [
                'wallet_id' => $toWallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $toBefore,
                'balance_after' => $toBefore + $amount,
                'reference_type' => 'wallet_transfer',
                'reference_id' => $transferId,
                'created_at' => now(),
            ],
        ]);

        $fromWallet->decrement('balance', $amount);
        $toWallet->increment('balance', $amount);
    }

    /**
     * Reversal penuh WalletTransfer: kembalikan saldo from_wallet (+amount) dan
     * to_wallet (-amount), hapus log balance terkait. Ditolak kalau to_wallet
     * sudah dipakai lagi sehingga saldo akan jadi negatif.
     */
    public function reverseTransfer(WalletTransfer $transfer): void
    {
        $fromWallet = $transfer->fromWallet;
        $toWallet = $transfer->toWallet;
        $amount = (float) $transfer->amount;

        if ((float) $toWallet->balance < $amount) {
            throw new InsufficientBalanceException(
                "Tidak bisa membatalkan transfer, saldo {$toWallet->display_name} sudah terpakai."
            );
        }

        DB::table('wallet_balance_logs')
            ->where('reference_type', 'wallet_transfer')
            ->where('reference_id', $transfer->id)
            ->delete();

        $toWallet->decrement('balance', $amount);
        $fromWallet->increment('balance', $amount);
    }
}
