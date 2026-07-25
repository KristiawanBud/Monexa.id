<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->index(['user_id', 'transferred_at'], 'idx_wt_user_transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->dropIndex('idx_wt_user_transferred_at');
        });
    }
};
