<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WalletTransferFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly string $fromWalletId,
        public readonly string $toWalletId,
        public readonly float $amount,
        public readonly float $fee,
        public readonly string $requestId,
        public readonly string $reason,
        public readonly int $durationMs,
    ) {}
}
