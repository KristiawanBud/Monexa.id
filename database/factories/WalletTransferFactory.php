<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransfer>
 */
class WalletTransferFactory extends Factory
{
    protected $model = WalletTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'from_wallet_id' => UserWallet::factory(),
            'to_wallet_id' => UserWallet::factory(),
            'amount' => fake()->randomFloat(2, 1000, 500000),
            'note' => null,
            'transferred_at' => now(),
        ];
    }
}
