<?php

namespace Tests\Feature;

use App\Models\WalletTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    public function test_delete_transfer_reverses_balances_and_removes_records(): void
    {
        $user = $this->createAppUser();
        $from = $this->createWallet($user, ['display_name' => 'Cash', 'balance' => 100000, 'sort_order' => 1]);
        $to = $this->createWallet($user, ['display_name' => 'Bank', 'balance' => 0, 'sort_order' => 2]);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $transfer = WalletTransfer::firstOrFail();

        $response = $this->actingAs($user)->delete(route('wallets.transfer.destroy', $transfer));

        $response->assertRedirect();
        $this->assertEquals(100000, (float) $from->fresh()->balance);
        $this->assertEquals(0, (float) $to->fresh()->balance);
        $this->assertDatabaseMissing('wallet_transfers', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('wallet_balance_logs', [
            'reference_type' => 'wallet_transfer',
            'reference_id' => $transfer->id,
        ]);
    }

    public function test_delete_transfer_rejected_when_to_wallet_balance_already_used(): void
    {
        $user = $this->createAppUser();
        $from = $this->createWallet($user, ['display_name' => 'Cash', 'balance' => 100000, 'sort_order' => 1]);
        $to = $this->createWallet($user, ['display_name' => 'Bank', 'balance' => 0, 'sort_order' => 2]);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $transfer = WalletTransfer::firstOrFail();

        // to_wallet sudah dipakai lagi setelah transfer masuk — saldo turun di bawah amount transfer
        $to->fresh()->update(['balance' => 10000]);

        $response = $this->actingAs($user)->delete(route('wallets.transfer.destroy', $transfer));

        $response->assertRedirect();
        $this->assertDatabaseHas('wallet_transfers', ['id' => $transfer->id]);
        $this->assertEquals(10000, (float) $to->fresh()->balance);
        $this->assertEquals(60000, (float) $from->fresh()->balance);
    }

    public function test_user_cannot_delete_another_users_transfer(): void
    {
        $owner = $this->createAppUser();
        $otherUser = $this->createAppUser();

        $from = $this->createWallet($owner, ['display_name' => 'Cash', 'balance' => 100000, 'sort_order' => 1]);
        $to = $this->createWallet($owner, ['display_name' => 'Bank', 'balance' => 0, 'sort_order' => 2]);

        $this->actingAs($owner)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $transfer = WalletTransfer::firstOrFail();

        $response = $this->actingAs($otherUser)->delete(route('wallets.transfer.destroy', $transfer));

        $response->assertForbidden();
        $this->assertDatabaseHas('wallet_transfers', ['id' => $transfer->id]);
    }
}
