<?php

namespace Tests\Unit\Models;

use App\Enums\WalletTransferStatus;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_cast_to_enum(): void
    {
        $transfer = WalletTransfer::factory()->create();

        $this->assertInstanceOf(WalletTransferStatus::class, $transfer->status);
    }

    public function test_default_status_from_factory_is_completed(): void
    {
        $transfer = WalletTransfer::factory()->create();

        $this->assertSame(WalletTransferStatus::Completed, $transfer->status);
    }

    public function test_status_defaults_to_completed_when_created_without_explicit_status(): void
    {
        $user = User::factory()->create();
        $fromWallet = UserWallet::factory()->create(['user_id' => $user->id]);
        $toWallet = UserWallet::factory()->create(['user_id' => $user->id]);

        $transfer = WalletTransfer::create([
            'user_id' => $user->id,
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => 1000,
            'transferred_at' => now(),
        ]);

        $this->assertSame(WalletTransferStatus::Completed, $transfer->status);
    }
}
