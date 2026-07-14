<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class DompetTransactionHistoryTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    public function test_transfer_appears_in_history_for_both_wallets_with_correct_direction(): void
    {
        $user = $this->createAppUser();
        $cash = $this->createWallet($user, ['display_name' => 'Cash', 'balance' => 100000, 'sort_order' => 1]);
        $bank = $this->createWallet($user, ['display_name' => 'Bank', 'balance' => 0, 'sort_order' => 2]);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $cash->id,
            'to_wallet_id' => $bank->id,
            'amount' => 25000,
            'transferred_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $response = $this->actingAs($user)->get(route('dompet.index'));
        $response->assertOk();

        $rows = collect($this->inertiaProps($response)['transactions']['data']);

        $outRow = $rows->firstWhere('type', 'transfer_out');
        $inRow = $rows->firstWhere('type', 'transfer_in');

        $this->assertNotNull($outRow);
        $this->assertNotNull($inRow);

        $this->assertSame($cash->id, $outRow['wallet_id']);
        $this->assertSame('Bank', $outRow['counterparty_wallet']);
        $this->assertSame(25000.0, $outRow['amount']);

        $this->assertSame($bank->id, $inRow['wallet_id']);
        $this->assertSame('Cash', $inRow['counterparty_wallet']);
        $this->assertSame(25000.0, $inRow['amount']);

        $this->assertSame($outRow['transfer_id'], $inRow['transfer_id']);
    }

    public function test_wallet_id_filter_shows_only_relevant_transfer_side(): void
    {
        $user = $this->createAppUser();
        $cash = $this->createWallet($user, ['display_name' => 'Cash', 'balance' => 100000, 'sort_order' => 1]);
        $bank = $this->createWallet($user, ['display_name' => 'Bank', 'balance' => 0, 'sort_order' => 2]);

        $this->actingAs($user)->post(route('wallets.transfer'), [
            'from_wallet_id' => $cash->id,
            'to_wallet_id' => $bank->id,
            'amount' => 25000,
            'transferred_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $response = $this->actingAs($user)->get(route('dompet.index', ['wallet_id' => $bank->id]));
        $response->assertOk();

        $rows = collect($this->inertiaProps($response)['transactions']['data']);

        $this->assertCount(1, $rows);
        $this->assertSame('transfer_in', $rows->first()['type']);
    }

    private function inertiaProps($response): array
    {
        $page = json_decode(json_encode($response->viewData('page')), true);

        return $page['props'];
    }
}
