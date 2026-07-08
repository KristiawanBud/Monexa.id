<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 80);
            $table->string('short_name', 20);
            $table->enum('type', ['conventional', 'syariah', 'digital', 'other']);
            $table->char('logo_color', 7)->nullable();
            $table->char('logo_initial', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('sort_order')->default(99);
        });

        Schema::create('user_wallets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_uw_user')
                  ->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('bank_id')->nullable();
            $table->foreign('bank_id', 'fk_uw_bank')
                  ->references('id')->on('banks')->nullOnDelete();
            $table->string('display_name', 60);
            $table->string('account_number', 30)->nullable();
            $table->enum('type', ['cash_flow', 'saving', 'both', 'investment'])->default('both');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_saham')->default(false);
            $table->decimal('saham_modal', 15, 2)->nullable();
            $table->decimal('saham_nilai_sekarang', 15, 2)->nullable();
            $table->tinyInteger('sort_order')->default(99);
            $table->timestamps();
            $table->index('user_id', 'idx_uw_user');
        });

        Schema::create('wallet_balance_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('wallet_id', 26)->nullable(false);
            $table->foreign('wallet_id', 'fk_wbl_wallet')
                  ->references('id')->on('user_wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type', 50)->nullable();
            $table->char('reference_id', 26)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('wallet_id', 'idx_wbl_wallet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balance_logs');
        Schema::dropIfExists('user_wallets');
        Schema::dropIfExists('banks');
    }
};
