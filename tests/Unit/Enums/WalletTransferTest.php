<?php

namespace Tests\Unit\Enums;

use App\Enums\WalletTransfer;
use PHPUnit\Framework\TestCase;

class WalletTransferTest extends TestCase
{
    public function test_debit_case_has_expected_value(): void
    {
        $this->assertSame('debit', WalletTransfer::Debit->value);
    }

    public function test_credit_case_has_expected_value(): void
    {
        $this->assertSame('credit', WalletTransfer::Credit->value);
    }

    public function test_from_resolves_credit(): void
    {
        $this->assertSame(WalletTransfer::Credit, WalletTransfer::from('credit'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(WalletTransfer::tryFrom('invalid'));
    }
}
