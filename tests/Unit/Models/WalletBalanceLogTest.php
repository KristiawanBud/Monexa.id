<?php

namespace Tests\Unit\Models;

use App\Enums\WalletTransfer;
use App\Models\UserWallet;
use App\Models\WalletBalanceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletBalanceLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_column_casts_to_wallet_transfer_enum(): void
    {
        $wallet = UserWallet::factory()->create();

        $log = WalletBalanceLog::create([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 10000,
            'balance_before' => 20000,
            'balance_after' => 10000,
            'reference_type' => 'transaction',
            'reference_id' => null,
        ]);

        $this->assertInstanceOf(WalletTransfer::class, $log->type);
        $this->assertSame(WalletTransfer::Debit, $log->type);
    }
}
