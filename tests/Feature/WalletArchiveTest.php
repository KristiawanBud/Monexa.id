<?php

namespace Tests\Feature;

use App\Models\UserWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class WalletArchiveTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    public function test_active_wallet_can_be_archived(): void
    {
        $user = $this->createAppUser();

        $wallet = UserWallet::create([
            'user_id' => $user->id,
            'display_name' => 'Dompet Aktif',
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->patch(route('wallets.archive', $wallet));

        $response->assertRedirect();
        $this->assertFalse($wallet->fresh()->is_active);
    }

    public function test_archived_wallet_can_be_reactivated(): void
    {
        $user = $this->createAppUser();

        $wallet = UserWallet::create([
            'user_id' => $user->id,
            'display_name' => 'Dompet Arsip',
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->patch(route('wallets.archive', $wallet));

        $response->assertRedirect();
        $this->assertTrue($wallet->fresh()->is_active);
    }

    public function test_user_cannot_archive_another_users_wallet(): void
    {
        $user = $this->createAppUser();
        $otherUser = $this->createAppUser();

        $wallet = UserWallet::create([
            'user_id' => $otherUser->id,
            'display_name' => 'Dompet Orang Lain',
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->patch(route('wallets.archive', $wallet))->assertForbidden();
        $this->assertTrue($wallet->fresh()->is_active);
    }
}
