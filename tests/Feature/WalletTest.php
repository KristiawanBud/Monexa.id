<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletTest extends TestCase
{
    /**
     * super_admin dipakai supaya request lolos middleware subscribed/onboarded
     * tanpa perlu setup Subscription/UserProfile — lihat
     * App\Http\Middleware\CheckSubscription & EnsureOnboarded.
     */
    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function makeWallet(User $user, array $overrides = []): UserWallet
    {
        return $user->wallets()->create(array_merge([
            'display_name' => 'Dompet '.Str::random(4),
            'type' => 'cash_flow',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => true,
            'currency' => 'IDR',
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_store_wallet_defaults_currency_to_idr(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('wallets.store'), [
                'display_name' => 'Dompet Cash',
                'type' => 'cash_flow',
                'initial_balance' => 100000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_wallets', [
            'user_id' => $user->id,
            'display_name' => 'Dompet Cash',
            'currency' => 'IDR',
        ]);
    }

    public function test_store_wallet_accepts_custom_currency(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('wallets.store'), [
                'display_name' => 'Dompet USD',
                'type' => 'saving',
                'currency' => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_wallets', [
            'user_id' => $user->id,
            'display_name' => 'Dompet USD',
            'currency' => 'USD',
        ]);
    }

    public function test_store_wallet_rejects_invalid_currency(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('wallets.store'), [
                'display_name' => 'Dompet Aneh',
                'type' => 'cash_flow',
                'currency' => 'XXX',
            ])
            ->assertSessionHasErrors('currency');
    }

    public function test_update_wallet_can_change_currency(): void
    {
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user);

        $this->actingAs($user)
            ->put(route('wallets.update', $wallet), [
                'display_name' => $wallet->display_name,
                'type' => $wallet->type,
                'currency' => 'EUR',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_wallets', [
            'id' => $wallet->id,
            'currency' => 'EUR',
        ]);
    }

    public function test_update_wallet_keeps_currency_when_not_sent(): void
    {
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user, ['currency' => 'SGD']);

        $this->actingAs($user)
            ->put(route('wallets.update', $wallet), [
                'display_name' => $wallet->display_name,
                'type' => $wallet->type,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_wallets', [
            'id' => $wallet->id,
            'currency' => 'SGD',
        ]);
    }

    public function test_set_primary_only_allows_one_primary_wallet(): void
    {
        $user = $this->makeUser();
        $walletA = $this->makeWallet($user, ['is_primary' => true, 'sort_order' => 1]);
        $walletB = $this->makeWallet($user, ['sort_order' => 2]);

        $this->actingAs($user)
            ->patch(route('wallets.setPrimary', $walletB))
            ->assertRedirect();

        $this->assertDatabaseHas('user_wallets', ['id' => $walletB->id, 'is_primary' => true]);
        $this->assertDatabaseHas('user_wallets', ['id' => $walletA->id, 'is_primary' => false]);
        $this->assertSame(1, UserWallet::where('user_id', $user->id)->where('is_primary', true)->count());
    }

    public function test_set_primary_rejects_archived_wallet(): void
    {
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user, ['is_active' => false]);

        $this->actingAs($user)
            ->patch(route('wallets.setPrimary', $wallet))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('user_wallets', ['id' => $wallet->id, 'is_primary' => false]);
    }

    public function test_set_primary_rejects_wallet_not_owned_by_user(): void
    {
        $user = $this->makeUser();
        $otherUser = $this->makeUser();
        $wallet = $this->makeWallet($otherUser);

        $this->actingAs($user)
            ->patch(route('wallets.setPrimary', $wallet))
            ->assertForbidden();
    }

    public function test_archive_reassigns_primary_to_oldest_remaining_active_wallet(): void
    {
        $user = $this->makeUser();
        $primary = $this->makeWallet($user, ['is_primary' => true, 'sort_order' => 1]);
        $older = $this->makeWallet($user, ['sort_order' => 2]);
        $this->makeWallet($user, ['sort_order' => 3]);

        $this->actingAs($user)
            ->patch(route('wallets.archive', $primary))
            ->assertRedirect();

        $this->assertDatabaseHas('user_wallets', ['id' => $primary->id, 'is_active' => false, 'is_primary' => false]);
        $this->assertDatabaseHas('user_wallets', ['id' => $older->id, 'is_primary' => true]);
    }

    public function test_archive_last_active_wallet_is_rejected(): void
    {
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user);

        $this->actingAs($user)
            ->patch(route('wallets.archive', $wallet))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('user_wallets', ['id' => $wallet->id, 'is_active' => true]);
    }

    public function test_restore_reactivates_wallet_without_making_it_primary(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, ['is_primary' => true, 'sort_order' => 1]);
        $archived = $this->makeWallet($user, ['is_active' => false, 'sort_order' => 2]);

        $this->actingAs($user)
            ->patch(route('wallets.restore', $archived))
            ->assertRedirect();

        $this->assertDatabaseHas('user_wallets', [
            'id' => $archived->id,
            'is_active' => true,
            'is_primary' => false,
        ]);
    }

    public function test_transfer_creates_two_linked_transactions_without_double_counting_balance(): void
    {
        $user = $this->makeUser();
        $fromWallet = $this->makeWallet($user, ['balance' => 500000, 'initial_balance' => 500000]);
        $toWallet = $this->makeWallet($user, ['balance' => 100000, 'initial_balance' => 100000]);

        $this->actingAs($user)
            ->post(route('wallets.transfer'), [
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'amount' => 150000,
                'note' => null,
                'transferred_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $fromWallet->refresh();
        $toWallet->refresh();

        $this->assertSame(350000.0, (float) $fromWallet->balance);
        $this->assertSame(250000.0, (float) $toWallet->balance);

        $expense = $user->transactions()->where('wallet_id', $fromWallet->id)->where('source', 'wallet_transfer')->first();
        $income = $user->transactions()->where('wallet_id', $toWallet->id)->where('source', 'wallet_transfer')->first();

        $this->assertNotNull($expense);
        $this->assertNotNull($income);
        $this->assertSame('expense', $expense->type);
        $this->assertSame('income', $income->type);
        $this->assertNull($expense->category_id);
        $this->assertNull($income->category_id);
        $this->assertNotNull($expense->transfer_id);
        $this->assertSame($expense->transfer_id, $income->transfer_id);
        $this->assertSame("Transfer ke {$toWallet->display_name}", $expense->note);
        $this->assertSame("Transfer dari {$fromWallet->display_name}", $income->note);

        // No double-count: exactly 2 wallet_balance_logs rows for this transfer,
        // and the sum of balance deltas matches the transferred amount on both sides.
        $logs = DB::table('wallet_balance_logs')
            ->whereIn('reference_id', [$expense->id, $income->id])
            ->get();

        $this->assertCount(2, $logs);

        foreach ($logs as $log) {
            $this->assertSame(150000.0, abs((float) $log->balance_after - (float) $log->balance_before));
        }
    }

    public function test_transfer_rejected_when_insufficient_balance(): void
    {
        $user = $this->makeUser();
        $fromWallet = $this->makeWallet($user, ['balance' => 50000, 'initial_balance' => 50000]);
        $toWallet = $this->makeWallet($user, ['balance' => 0]);

        $this->actingAs($user)
            ->post(route('wallets.transfer'), [
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'amount' => 100000,
                'transferred_at' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('error');

        $fromWallet->refresh();
        $toWallet->refresh();

        $this->assertSame(50000.0, (float) $fromWallet->balance);
        $this->assertSame(0.0, (float) $toWallet->balance);
        $this->assertSame(0, $user->transactions()->where('source', 'wallet_transfer')->count());
    }

    public function test_wallet_transfer_transaction_cannot_be_updated_or_deleted_directly(): void
    {
        $user = $this->makeUser();
        $fromWallet = $this->makeWallet($user, ['balance' => 500000, 'initial_balance' => 500000]);
        $toWallet = $this->makeWallet($user, ['balance' => 0]);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => 100000,
            'transferred_at' => now()->format('Y-m-d'),
        ]);

        $transferTransaction = $user->transactions()->where('source', 'wallet_transfer')->firstOrFail();

        $this->actingAs($user)
            ->put(route('dompet.update', $transferTransaction), [
                'type' => 'expense',
                'amount' => 1,
                'wallet_id' => $fromWallet->id,
                'transacted_at' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->delete(route('dompet.destroy', $transferTransaction))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('transactions', ['id' => $transferTransaction->id, 'deleted_at' => null]);
    }
}
