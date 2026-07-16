<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('source')->default('manual')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN source ENUM(
            'manual', 'wa_bot', 'wa_receipt', 'import', 'bill_payment', 'saving_deposit', 'cuanai_chat'
        ) NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('source')->default('manual')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN source ENUM(
            'manual', 'wa_bot', 'wa_receipt', 'import', 'bill_payment', 'saving_deposit'
        ) NOT NULL DEFAULT 'manual'");
    }
};
