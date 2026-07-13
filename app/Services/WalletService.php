<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\SavingDeposit;
use App\Models\Transaction;
use App\Models\UserWallet;
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
     * Transfer saldo antar dompet milik user yang sama. Dicatat sebagai 2 baris
     * Transaction (expense di dompet asal, income di dompet tujuan) yang terhubung
     * via transfer_id, lewat applyTransaction() supaya saldo + wallet_balance_logs
     * konsisten dengan transaksi biasa (hindari double-mutation saldo).
     */
    public function transferBetweenWallets(
        UserWallet $fromWallet,
        UserWallet $toWallet,
        float $amount,
        string $transferId,
        string $transferredAt,
        ?string $note = null
    ): void {
        if ((float) $fromWallet->balance < $amount) {
            throw new InsufficientBalanceException(
                "Saldo {$fromWallet->display_name} tidak cukup untuk transfer ini."
            );
        }

        $expenseTransaction = Transaction::create([
            'user_id' => $fromWallet->user_id,
            'wallet_id' => $fromWallet->id,
            'category_id' => null,
            'type' => 'expense',
            'amount' => $amount,
            'note' => $note ?? "Transfer ke {$toWallet->display_name}",
            'transacted_at' => $transferredAt,
            'source' => 'wallet_transfer',
            'transfer_id' => $transferId,
            'created_by' => $fromWallet->user_id,
        ]);
        $this->applyTransaction($expenseTransaction);

        $incomeTransaction = Transaction::create([
            'user_id' => $toWallet->user_id,
            'wallet_id' => $toWallet->id,
            'category_id' => null,
            'type' => 'income',
            'amount' => $amount,
            'note' => $note ?? "Transfer dari {$fromWallet->display_name}",
            'transacted_at' => $transferredAt,
            'source' => 'wallet_transfer',
            'transfer_id' => $transferId,
            'created_by' => $toWallet->user_id,
        ]);
        $this->applyTransaction($incomeTransaction);
    }
}
