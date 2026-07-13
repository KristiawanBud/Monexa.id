<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->char('transfer_id', 26)->nullable()->after('source');
            $table->foreign('transfer_id', 'fk_tx_transfer')
                ->references('id')->on('wallet_transfers')->nullOnDelete();
            $table->index('transfer_id', 'idx_tx_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('fk_tx_transfer');
            $table->dropIndex('idx_tx_transfer');
            $table->dropColumn('transfer_id');
        });
    }
};
