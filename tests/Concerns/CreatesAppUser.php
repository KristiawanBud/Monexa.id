<?php

namespace Tests\Concerns;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserWallet;

trait CreatesAppUser
{
    /**
     * User lengkap dengan profile + subscription aktif, supaya lolos
     * middleware 'subscribed' dan 'onboarded' di route group App.
     */
    protected function createAppUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'wa_number' => '628123456789',
        ], $attributes));

        UserProfile::create(['user_id' => $user->id]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return $user->refresh();
    }

    protected function createWallet(User $user, array $attributes = []): UserWallet
    {
        return UserWallet::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'Dompet Test',
            'type' => 'cash_flow',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }
}
