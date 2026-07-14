<?php

namespace Tests\Feature;

use App\Models\UserWallet;
use App\Models\WalletTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    private function makeWallet(string $userId, float $balance): UserWallet
    {
        return UserWallet::create([
            'user_id' => $userId,
            'display_name' => 'Dompet',
            'type' => 'both',
            'balance' => $balance,
            'initial_balance' => $balance,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_transfer_moves_balance_between_own_wallets(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertSame(60000.0, (float) $from->fresh()->balance);
        $this->assertSame(40000.0, (float) $to->fresh()->balance);
    }

    public function test_transfer_rejected_when_balance_insufficient(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 10000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(10000.0, (float) $from->fresh()->balance);
    }

    public function test_reversing_transfer_restores_balances_and_deletes_record(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ]);

        $transfer = WalletTransfer::firstOrFail();

        $response = $this->actingAs($user)->delete(route('wallets.transfer.destroy', $transfer));

        $response->assertRedirect();
        $this->assertSame(100000.0, (float) $from->fresh()->balance);
        $this->assertSame(0.0, (float) $to->fresh()->balance);
        $this->assertDatabaseMissing('wallet_transfers', ['id' => $transfer->id]);
    }

    public function test_reversal_rejected_when_destination_balance_already_spent(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ]);

        $transfer = WalletTransfer::firstOrFail();

        // Dompet tujuan sudah dipakai lagi hingga saldo tidak cukup untuk reversal
        $to->fresh()->update(['balance' => 10000]);

        $response = $this->actingAs($user)->delete(route('wallets.transfer.destroy', $transfer));

        $response->assertRedirect();
        $this->assertDatabaseHas('wallet_transfers', ['id' => $transfer->id]);
        $this->assertSame(10000.0, (float) $to->fresh()->balance);
    }

    public function test_user_cannot_reverse_another_users_transfer(): void
    {
        $owner = $this->createAppUser();
        $intruder = $this->createAppUser();
        $from = $this->makeWallet($owner->id, 100000);
        $to = $this->makeWallet($owner->id, 0);

        $this->actingAs($owner)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
        ]);

        $transfer = WalletTransfer::firstOrFail();

        $this->actingAs($intruder)->delete(route('wallets.transfer.destroy', $transfer))->assertForbidden();
        $this->assertDatabaseHas('wallet_transfers', ['id' => $transfer->id]);
    }
}
