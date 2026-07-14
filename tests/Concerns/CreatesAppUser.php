<?php

namespace Tests\Concerns;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;

trait CreatesAppUser
{
    /**
     * Buat user yang sudah lolos middleware 'onboarded' & 'subscribed',
     * supaya route App bisa diakses langsung di test tanpa redirect.
     */
    protected function createAppUser(array $userAttributes = [], array $profileAttributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'wa_number' => '628123456789',
            'role' => 'user',
        ], $userAttributes));

        UserProfile::create(array_merge([
            'user_id' => $user->id,
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ], $profileAttributes));

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return $user->fresh(['profile', 'subscription']);
    }
}
