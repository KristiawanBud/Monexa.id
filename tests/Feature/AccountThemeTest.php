<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class AccountThemeTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    public function test_user_can_update_theme(): void
    {
        $user = $this->createAppUser();

        $response = $this->actingAs($user)->put(route('account.theme'), ['theme' => 'dark']);

        $response->assertRedirect();
        $this->assertEquals('dark', $user->profile->fresh()->theme);
    }

    public function test_theme_value_outside_whitelist_is_rejected(): void
    {
        $user = $this->createAppUser();

        $response = $this->actingAs($user)->put(route('account.theme'), ['theme' => 'purple']);

        $response->assertSessionHasErrors('theme');
        $this->assertNull($user->profile->fresh()->theme);
    }

    public function test_updating_theme_does_not_affect_other_users(): void
    {
        $user = $this->createAppUser();
        $otherUser = $this->createAppUser();

        $this->actingAs($user)->put(route('account.theme'), ['theme' => 'green']);

        $this->assertEquals('green', $user->profile->fresh()->theme);
        $this->assertNull($otherUser->profile->fresh()->theme);
    }
}
