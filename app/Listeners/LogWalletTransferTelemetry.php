<?php

namespace App\Listeners;

use App\Events\WalletTransferFailed;
use App\Events\WalletTransferInitiated;
use App\Events\WalletTransferSucceeded;
use Illuminate\Support\Facades\Log;

class LogWalletTransferTelemetry
{
    public function handleInitiated(WalletTransferInitiated $event): void
    {
        Log::info('wallet_transfer_initiated', [
            'user_id' => $event->userId,
            'from_wallet_id' => $event->fromWalletId,
            'to_wallet_id' => $event->toWalletId,
            'amount' => $event->amount,
            'fee' => $event->fee,
            'request_id' => $event->requestId,
        ]);
    }

    public function handleSucceeded(WalletTransferSucceeded $event): void
    {
        Log::info('wallet_transfer_succeeded', [
            'user_id' => $event->userId,
            'from_wallet_id' => $event->fromWalletId,
            'to_wallet_id' => $event->toWalletId,
            'amount' => $event->amount,
            'fee' => $event->fee,
            'request_id' => $event->requestId,
            'wallet_transfer_id' => $event->walletTransferId,
            'duration_ms' => $event->durationMs,
        ]);
    }

    public function handleFailed(WalletTransferFailed $event): void
    {
        Log::info('wallet_transfer_failed', [
            'user_id' => $event->userId,
            'from_wallet_id' => $event->fromWalletId,
            'to_wallet_id' => $event->toWalletId,
            'amount' => $event->amount,
            'fee' => $event->fee,
            'request_id' => $event->requestId,
            'reason' => $event->reason,
            'duration_ms' => $event->durationMs,
        ]);
    }
}
