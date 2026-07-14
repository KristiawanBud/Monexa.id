<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class WalletArchiveTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    public function test_archive_toggles_is_active(): void
    {
        $user = $this->createAppUser();
        $wallet = $this->createWallet($user, ['is_active' => true]);

        $this->actingAs($user)->patch(route('wallets.archive', $wallet))->assertRedirect();
        $this->assertFalse($wallet->fresh()->is_active);

        $this->actingAs($user)->patch(route('wallets.archive', $wallet))->assertRedirect();
        $this->assertTrue($wallet->fresh()->is_active);
    }

    public function test_cannot_archive_another_users_wallet(): void
    {
        $owner = $this->createAppUser();
        $otherUser = $this->createAppUser();
        $wallet = $this->createWallet($owner, ['is_active' => true]);

        $response = $this->actingAs($otherUser)->patch(route('wallets.archive', $wallet));

        $response->assertForbidden();
        $this->assertTrue($wallet->fresh()->is_active);
    }
}
