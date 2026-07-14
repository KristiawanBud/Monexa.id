<?php

namespace Tests\Unit\Enums;

use App\Enums\WalletTransferStatus;
use PHPUnit\Framework\TestCase;

class WalletTransferStatusTest extends TestCase
{
    public function test_case_values(): void
    {
        $this->assertSame('pending', WalletTransferStatus::Pending->value);
        $this->assertSame('completed', WalletTransferStatus::Completed->value);
        $this->assertSame('failed', WalletTransferStatus::Failed->value);
    }

    public function test_from_resolves_case_by_value(): void
    {
        $this->assertSame(WalletTransferStatus::Completed, WalletTransferStatus::from('completed'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(WalletTransferStatus::tryFrom('invalid'));
    }
}
