<?php

namespace Tests\Feature;

use App\Enums\WalletTransferStatus;
use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_transfer_creates_wallet_transfer_with_completed_status(): void
    {
        $user = User::factory()->create();
        $fromWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 100000]);
        $toWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 0]);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => 50000,
            'note' => 'Test transfer',
            'transferred_at' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('wallet_transfers', [
            'user_id' => $user->id,
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'status' => WalletTransferStatus::Completed->value,
        ]);

        $this->assertEquals(50000, $fromWallet->fresh()->balance);
        $this->assertEquals(50000, $toWallet->fresh()->balance);
    }

    public function test_transfer_fails_when_balance_insufficient(): void
    {
        $user = User::factory()->create();
        $fromWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $toWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 0]);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => 50000,
            'transferred_at' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('wallet_transfers', [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
        ]);
    }

    public function test_status_is_not_accepted_as_client_input(): void
    {
        $user = User::factory()->create();
        $fromWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 100000]);
        $toWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 0]);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => 10000,
            'transferred_at' => now()->toDateString(),
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('wallet_transfers', [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'status' => WalletTransferStatus::Completed->value,
        ]);
    }
}
