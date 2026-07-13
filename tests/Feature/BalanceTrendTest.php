<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BalanceTrendTest extends TestCase
{
    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function makeWallet(User $user, array $overrides = []): UserWallet
    {
        return $user->wallets()->create(array_merge([
            'display_name' => 'Dompet '.Str::random(4),
            'type' => 'cash_flow',
            'balance' => 0,
            'initial_balance' => 0,
            'is_active' => true,
            'currency' => 'IDR',
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_range_param_is_required_and_validated(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson(route('dompet.balanceTrend'))
            ->assertStatus(422);

        $this->actingAs($user)
            ->getJson(route('dompet.balanceTrend', ['range' => 'invalid']))
            ->assertStatus(422);
    }

    public function test_wallet_without_any_balance_logs_returns_flat_current_balance(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, ['balance' => 250000]);

        $response = $this->actingAs($user)
            ->getJson(route('dompet.balanceTrend', ['range' => '7d']))
            ->assertOk()
            ->json();

        $this->assertSame('7d', $response['range']);
        $this->assertCount(7, $response['points']);

        foreach ($response['points'] as $point) {
            $this->assertSame(250000.0, (float) $point['total_balance']);
        }
    }

    public function test_points_forward_fill_and_reflect_logged_balance_changes(): void
    {
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user, ['balance' => 150000]);

        $today = now()->startOfDay();

        DB::table('wallet_balance_logs')->insert([
            [
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => 100000,
                'balance_before' => 0,
                'balance_after' => 100000,
                'reference_type' => 'transaction',
                'reference_id' => (string) Str::ulid(),
                'created_at' => $today->copy()->subDays(2),
            ],
            [
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => 50000,
                'balance_before' => 100000,
                'balance_after' => 150000,
                'reference_type' => 'transaction',
                'reference_id' => (string) Str::ulid(),
                'created_at' => $today->copy()->subDays(1),
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('dompet.balanceTrend', ['range' => '7d']))
            ->assertOk()
            ->json();

        $points = collect($response['points'])->keyBy('date');

        $dayBeforeFirstLog = $today->copy()->subDays(3)->format('Y-m-d');
        $dayOfFirstLog = $today->copy()->subDays(2)->format('Y-m-d');
        $dayOfSecondLog = $today->copy()->subDays(1)->format('Y-m-d');
        $dayAfterSecondLog = $today->format('Y-m-d');

        // Sebelum log pertama ada — forward fill pakai log paling awal yang tersedia.
        $this->assertSame(100000.0, (float) $points[$dayBeforeFirstLog]['total_balance']);
        $this->assertSame(100000.0, (float) $points[$dayOfFirstLog]['total_balance']);
        // Begitu ada log baru, nilai ikut naik.
        $this->assertSame(150000.0, (float) $points[$dayOfSecondLog]['total_balance']);
        // Setelah log terakhir, tetap forward-filled dengan nilai terbaru.
        $this->assertSame(150000.0, (float) $points[$dayAfterSecondLog]['total_balance']);
    }

    public function test_sums_balance_across_multiple_active_wallets_and_excludes_archived(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, ['balance' => 100000]);
        $this->makeWallet($user, ['balance' => 50000]);
        $this->makeWallet($user, ['balance' => 999999, 'is_active' => false]);

        $response = $this->actingAs($user)
            ->getJson(route('dompet.balanceTrend', ['range' => '30d']))
            ->assertOk()
            ->json();

        $this->assertCount(30, $response['points']);

        foreach ($response['points'] as $point) {
            $this->assertSame(150000.0, (float) $point['total_balance']);
        }
    }
}
