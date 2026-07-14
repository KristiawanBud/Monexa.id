<?php

namespace Tests\Feature;

use App\Models\UserWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class DompetTransactionHistoryTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    public function test_wallets_response_includes_icon_and_color(): void
    {
        $user = $this->createAppUser();

        UserWallet::create([
            'user_id' => $user->id,
            'display_name' => 'Dompet Custom',
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'icon' => '🐷',
            'color' => 'success',
        ]);

        $response = $this->actingAs($user)->get(route('dompet.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('wallets.0.icon', '🐷')
            ->where('wallets.0.color', 'success')
        );
    }

    public function test_archived_wallets_is_empty_when_show_archived_not_sent(): void
    {
        $user = $this->createAppUser();

        UserWallet::create([
            'user_id' => $user->id,
            'display_name' => 'Dompet Arsip',
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('dompet.index'));

        $response->assertInertia(fn ($page) => $page->where('archived_wallets', []));
    }

    public function test_archived_wallets_returns_only_own_inactive_wallets_when_requested(): void
    {
        $user = $this->createAppUser();
        $otherUser = $this->createAppUser();

        $ownArchived = UserWallet::create([
            'user_id' => $user->id,
            'display_name' => 'Dompet Lama',
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        UserWallet::create([
            'user_id' => $otherUser->id,
            'display_name' => 'Dompet Orang Lain',
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('dompet.index', ['show_archived' => 1]));

        $response->assertInertia(fn ($page) => $page
            ->has('archived_wallets', 1)
            ->where('archived_wallets.0.id', $ownArchived->id)
            ->where('archived_wallets.0.is_active', false)
        );
    }
}
