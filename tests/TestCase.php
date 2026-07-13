<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // NOTE: phpunit.xml sets DB_CONNECTION=sqlite. Migrations
    // 2026_07_08_000001_add_cuanai_chat_to_transactions_source_enum and
    // 2026_07_12_000003_add_wallet_transfer_to_transactions_source_enum run raw
    // MySQL `ALTER TABLE ... MODIFY COLUMN ... ENUM(...)` SQL, which SQLite's grammar
    // does not support — RefreshDatabase will fail at those migrations unless the
    // testing DB connection is pointed at MySQL instead. Flagging for Database AI/CEO
    // since migrations are out of Backend AI's scope to change.
    use RefreshDatabase;
}
