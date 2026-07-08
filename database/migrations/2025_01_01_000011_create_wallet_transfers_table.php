<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_wt_user')
                  ->references('id')->on('users')->cascadeOnDelete();

            $table->char('from_wallet_id', 26)->nullable(false);
            $table->foreign('from_wallet_id', 'fk_wt_from')
                  ->references('id')->on('user_wallets')->restrictOnDelete();

            $table->char('to_wallet_id', 26)->nullable(false);
            $table->foreign('to_wallet_id', 'fk_wt_to')
                  ->references('id')->on('user_wallets')->restrictOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('note', 255)->nullable();
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->index('user_id', 'idx_wt_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transfers');
    }
};
