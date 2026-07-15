<?php

namespace Tests\Unit;

use App\Models\UserWallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAppUser;
use Tests\TestCase;

class WalletServiceFeeTest extends TestCase
{
    use CreatesAppUser, RefreshDatabase;

    private function makeWallet(string $userId, float $balance): UserWallet
    {
        return UserWallet::create([
            'user_id' => $userId,
            'display_name' => 'Dompet',
            'type' => 'both',
            'balance' => $balance,
            'initial_balance' => $balance,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_transfer_with_fee_writes_three_balance_logs_that_balance_out(): void
    {
        $user = $this->createAppUser();
        $from = $this->makeWallet($user->id, 100000);
        $to = $this->makeWallet($user->id, 0);
        $transferId = (string) Str::ulid();

        (new WalletService)->transferBetweenWallets($from, $to, 40000, $transferId, 2500);

        $logs = DB::table('wallet_balance_logs')->where('reference_id', $transferId)->get();

        $this->assertCount(3, $logs);

        $totalDebit = $logs->where('type', 'debit')->sum('amount');
        $totalCredit = $logs->where('type', 'credit')->sum('amount');

        $this->assertSame(42500.0, (float) $totalDebit);
        $this->assertSame(40000.0, (float) $totalCredit);
        $this->assertSame((float) $totalCredit + 2500, (float) $totalDebit);

        $feeLog = $logs->firstWhere('reference_type', 'wallet_transfer_fee');
        $this->assertNotNull($feeLog);
        $this->assertSame('debit', $feeLog->type);
        $this->assertSame(2500.0, (float) $feeLog->amount);

        $this->assertSame(57500.0, (float) $from->fresh()->balance);
        $this->assertSame(40000.0, (float) $to->fresh()->balance);
    }
}
