<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWallet>
 */
class UserWalletFactory extends Factory
{
    protected $model = UserWallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => null,
            'display_name' => fake()->words(2, true),
            'account_number' => null,
            'type' => 'both',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => true,
            'is_saham' => false,
            'sort_order' => 1,
        ];
    }
}
