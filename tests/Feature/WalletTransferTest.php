<?php

namespace Tests\Feature;

use App\Events\WalletTransferFailed;
use App\Events\WalletTransferInitiated;
use App\Events\WalletTransferSucceeded;
use App\Models\TransactionCategory;
use App\Models\UserWallet;
use App\Models\WalletTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
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
            'request_id' => (string) Str::uuid(),
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
            'request_id' => (string) Str::uuid(),
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
            'request_id' => (string) Str::uuid(),
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
            'request_id' => (string) Str::uuid(),
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
            'request_id' => (string) Str::uuid(),
        ]);

        $transfer = WalletTransfer::firstOrFail();

        $this->actingAs($intruder)->delete(route('wallets.transfer.destroy', $transfer))->assertForbidden();
        $this->assertDatabaseHas('wallet_transfers', ['id' => $transfer->id]);
    }

    public function test_user_cannot_transfer_from_another_users_wallet(): void
    {
        $owner = $this->createAppUser();
        $intruder = $this->createAppUser();
        $from = $this->makeWallet($owner->id, 100000);
        $to = $this->makeWallet($intruder->id, 0);

        $this->actingAs($intruder)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->assertSame(100000.0, (float) $from->fresh()->balance);
    }

    public function test_user_cannot_transfer_to_another_users_wallet(): void
    {
        $owner = $this->createAppUser();
        $intruder = $this->createAppUser();
        $from = $this->makeWallet($intruder->id, 100000);
        $to = $this->makeWallet($owner->id, 0);

        $this->actingAs($intruder)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->assertSame(100000.0, (float) $from->fresh()->balance);
        $this->assertSame(0.0, (float) $to->fresh()->balance);
    }

    public function test_transfer_with_fee_deducts_amount_plus_fee_from_source_only(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'fee' => 2500,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $this->assertSame(57500.0, (float) $from->fresh()->balance);
        $this->assertSame(40000.0, (float) $to->fresh()->balance);
        $this->assertSame(2500.0, (float) WalletTransfer::firstOrFail()->fee);
    }

    public function test_transfer_rejected_when_balance_covers_amount_but_not_fee(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 40000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'fee' => 2500,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(40000.0, (float) $from->fresh()->balance);
        $this->assertSame(0.0, (float) $to->fresh()->balance);
        $this->assertDatabaseCount('wallet_transfers', 0);
    }

    public function test_reversing_transfer_with_fee_refunds_amount_plus_fee(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'fee' => 2500,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $transfer = WalletTransfer::firstOrFail();

        $response = $this->actingAs($user)->delete(route('wallets.transfer.destroy', $transfer));

        $response->assertRedirect();
        $this->assertSame(100000.0, (float) $from->fresh()->balance);
        $this->assertSame(0.0, (float) $to->fresh()->balance);
    }

    public function test_duplicate_request_id_does_not_double_transfer(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);
        $requestId = (string) Str::uuid();

        $payload = [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => $requestId,
        ];

        $first = $this->actingAs($user)->post(route('wallets.transfer'), $payload);
        $second = $this->actingAs($user)->post(route('wallets.transfer'), $payload);

        $first->assertRedirect();
        $second->assertRedirect();
        $second->assertSessionHas('success');
        $this->assertDatabaseCount('wallet_transfers', 1);
        $this->assertSame(60000.0, (float) $from->fresh()->balance);
        $this->assertSame(40000.0, (float) $to->fresh()->balance);
    }

    public function test_different_request_id_with_same_payload_creates_separate_transfers(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $payload = [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 20000,
            'transferred_at' => now()->format('Y-m-d'),
        ];

        $this->actingAs($user)->post(route('wallets.transfer'), $payload + ['request_id' => (string) Str::uuid()]);
        $this->actingAs($user)->post(route('wallets.transfer'), $payload + ['request_id' => (string) Str::uuid()]);

        $this->assertDatabaseCount('wallet_transfers', 2);
        $this->assertSame(60000.0, (float) $from->fresh()->balance);
        $this->assertSame(40000.0, (float) $to->fresh()->balance);
    }

    public function test_successful_transfer_dispatches_initiated_and_succeeded_events(): void
    {
        Event::fake();

        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        Event::assertDispatched(WalletTransferInitiated::class, 1);
        Event::assertDispatched(WalletTransferSucceeded::class, 1);
        Event::assertNotDispatched(WalletTransferFailed::class);
    }

    public function test_failed_transfer_dispatches_failed_event_not_succeeded(): void
    {
        Event::fake();

        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 10000);
        $to = $this->makeWallet($user->id, 0);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        Event::assertDispatched(WalletTransferInitiated::class, 1);
        Event::assertDispatched(WalletTransferFailed::class, 1);
        Event::assertNotDispatched(WalletTransferSucceeded::class);
    }

    public function test_transfer_with_valid_category_id_is_persisted(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);
        $category = TransactionCategory::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'name' => 'Tabungan',
            'is_system' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'category_id' => $category->id,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $this->assertSame($category->id, WalletTransfer::firstOrFail()->category_id);
    }

    public function test_transfer_with_nonexistent_category_id_is_rejected(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'category_id' => 999999,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('wallet_transfers', 0);
    }

    public function test_transfer_without_category_id_still_succeeds(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull(WalletTransfer::firstOrFail()->category_id);
    }

    public function test_transfer_exceeding_configured_max_amount_is_rejected(): void
    {
        config(['wallet.max_transfer_amount' => 50000]);

        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 200000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 100000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('wallet_transfers', 0);
    }

    public function test_transfer_below_configured_max_amount_still_succeeds(): void
    {
        config(['wallet.max_transfer_amount' => 50000]);

        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 200000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 40000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_transfer_with_default_max_amount_config_has_no_limit(): void
    {
        $this->assertNull(config('wallet.max_transfer_amount'));

        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 10000000);
        $to = $this->makeWallet($user->id, 0);

        $response = $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 5000000,
            'transferred_at' => now()->format('Y-m-d'),
            'request_id' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
