<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransfer;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletServiceReverseTransferTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransferWithLogs(User $user, UserWallet $from, UserWallet $to, float $amount): WalletTransfer
    {
        $transfer = WalletTransfer::create([
            'user_id' => $user->id,
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => $amount,
            'transferred_at' => now(),
        ]);

        DB::table('wallet_balance_logs')->insert([
            [
                'wallet_id' => $from->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => (float) $from->balance + $amount,
                'balance_after' => (float) $from->balance,
                'reference_type' => 'wallet_transfer',
                'reference_id' => $transfer->id,
                'created_at' => now(),
            ],
            [
                'wallet_id' => $to->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => (float) $to->balance - $amount,
                'balance_after' => (float) $to->balance,
                'reference_type' => 'wallet_transfer',
                'reference_id' => $transfer->id,
                'created_at' => now(),
            ],
        ]);

        return $transfer;
    }

    public function test_reverse_transfer_restores_balances_and_deletes_logs(): void
    {
        $user = User::factory()->create();

        $from = UserWallet::create([
            'user_id' => $user->id, 'display_name' => 'Cash', 'type' => 'cash_flow',
            'balance' => 50000, 'initial_balance' => 100000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $to = UserWallet::create([
            'user_id' => $user->id, 'display_name' => 'Bank', 'type' => 'saving',
            'balance' => 150000, 'initial_balance' => 100000, 'is_active' => true, 'sort_order' => 2,
        ]);

        $transfer = $this->makeTransferWithLogs($user, $from, $to, 50000);

        app(WalletService::class)->reverseTransfer($transfer);

        $this->assertEquals(100000, (float) $from->fresh()->balance);
        $this->assertEquals(100000, (float) $to->fresh()->balance);
        $this->assertDatabaseMissing('wallet_balance_logs', [
            'reference_type' => 'wallet_transfer',
            'reference_id' => $transfer->id,
        ]);
    }

    public function test_reverse_transfer_throws_when_to_wallet_balance_already_used(): void
    {
        $user = User::factory()->create();

        $from = UserWallet::create([
            'user_id' => $user->id, 'display_name' => 'Cash', 'type' => 'cash_flow',
            'balance' => 50000, 'initial_balance' => 100000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $to = UserWallet::create([
            'user_id' => $user->id, 'display_name' => 'Bank', 'type' => 'saving',
            'balance' => 20000, 'initial_balance' => 100000, 'is_active' => true, 'sort_order' => 2,
        ]);

        $transfer = $this->makeTransferWithLogs($user, $from, $to, 50000);

        try {
            app(WalletService::class)->reverseTransfer($transfer);
            $this->fail('Expected InsufficientBalanceException was not thrown.');
        } catch (InsufficientBalanceException $e) {
            // expected
        }

        $this->assertEquals(50000, (float) $from->fresh()->balance);
        $this->assertEquals(20000, (float) $to->fresh()->balance);
        $this->assertDatabaseHas('wallet_balance_logs', [
            'reference_type' => 'wallet_transfer',
            'reference_id' => $transfer->id,
        ]);
    }
}
