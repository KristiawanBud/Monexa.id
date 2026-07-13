<?php

namespace Tests\Feature;

use App\Enums\WalletTransfer;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletBalanceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_transfer_between_own_wallets_succeeds(): void
    {
        $user = $this->actingUser();
        $fromWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 100000]);
        $toWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 0]);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => 40000,
            'note' => 'Transfer test',
            'transferred_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('wallet_transfers', [
            'user_id' => $user->id,
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
        ]);

        $this->assertSame(60000.0, (float) $fromWallet->fresh()->balance);
        $this->assertSame(40000.0, (float) $toWallet->fresh()->balance);

        $debitLog = WalletBalanceLog::where('wallet_id', $fromWallet->id)
            ->where('reference_type', 'wallet_transfer')->first();
        $creditLog = WalletBalanceLog::where('wallet_id', $toWallet->id)
            ->where('reference_type', 'wallet_transfer')->first();

        $this->assertNotNull($debitLog);
        $this->assertNotNull($creditLog);
        $this->assertSame(WalletTransfer::Debit, $debitLog->type);
        $this->assertSame(WalletTransfer::Credit, $creditLog->type);
    }

    public function test_transfer_fails_when_balance_insufficient(): void
    {
        $user = $this->actingUser();
        $fromWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $toWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 0]);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => 5000,
            'transferred_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertSame(1000.0, (float) $fromWallet->fresh()->balance);
        $this->assertSame(0.0, (float) $toWallet->fresh()->balance);
    }

    public function test_transfer_rejected_when_wallet_not_owned_by_user(): void
    {
        $user = $this->actingUser();
        $otherUser = User::factory()->create(['role' => 'super_admin']);

        $fromWallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 100000]);
        $foreignWallet = UserWallet::factory()->create(['user_id' => $otherUser->id, 'balance' => 0]);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $foreignWallet->id,
            'amount' => 1000,
            'transferred_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertForbidden();
    }

    public function test_transfer_rejected_when_from_and_to_wallet_are_same(): void
    {
        $user = $this->actingUser();
        $wallet = UserWallet::factory()->create(['user_id' => $user->id, 'balance' => 100000]);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $wallet->id,
            'to_wallet_id' => $wallet->id,
            'amount' => 1000,
            'transferred_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors(['from_wallet_id']);
    }
}
