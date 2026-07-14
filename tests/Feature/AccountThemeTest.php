<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class AccountThemeTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    public function test_theme_can_be_updated_to_a_valid_value(): void
    {
        $user = $this->createAppUser();

        $response = $this->actingAs($user)->put(route('account.theme'), ['theme' => 'dark']);

        $response->assertRedirect();
        $this->assertSame('dark', $user->profile->fresh()->theme);
    }

    public function test_theme_accepts_system_value(): void
    {
        $user = $this->createAppUser();

        $response = $this->actingAs($user)->put(route('account.theme'), ['theme' => 'system']);

        $response->assertRedirect();
        $this->assertSame('system', $user->profile->fresh()->theme);
    }

    public function test_theme_rejects_value_outside_whitelist(): void
    {
        $user = $this->createAppUser();

        $response = $this->actingAs($user)->put(route('account.theme'), ['theme' => 'purple']);

        $response->assertSessionHasErrors('theme');
    }
}
