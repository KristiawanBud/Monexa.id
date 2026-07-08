<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN source ENUM(
            'manual', 'wa_bot', 'wa_receipt', 'import', 'bill_payment', 'saving_deposit', 'cuanai_chat'
        ) NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN source ENUM(
            'manual', 'wa_bot', 'wa_receipt', 'import', 'bill_payment', 'saving_deposit'
        ) NOT NULL DEFAULT 'manual'");
    }
};
