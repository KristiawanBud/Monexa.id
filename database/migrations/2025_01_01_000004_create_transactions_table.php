<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_message_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable();
            $table->foreign('user_id', 'fk_wml_user')
                  ->references('id')->on('users')->nullOnDelete();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->string('from_number', 25);
            $table->text('message');
            $table->string('intent', 50)->nullable();
            $table->json('parsed_data')->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->string('error_message', 255)->nullable();
            $table->timestamp('received_at')->useCurrent();
        });

        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->char('user_id', 26)->nullable();
            $table->foreign('user_id', 'fk_tc_user')
                  ->references('id')->on('users')->nullOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('name', 50);
            $table->string('emoji', 10)->nullable();
            $table->boolean('is_system')->default(false);
            $table->tinyInteger('sort_order')->default(99);
            $table->index('user_id', 'idx_tc_user');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_tx_user')
                  ->references('id')->on('users')->cascadeOnDelete();
            $table->char('wallet_id', 26)->nullable(false);
            $table->foreign('wallet_id', 'fk_tx_wallet')
                  ->references('id')->on('user_wallets')->restrictOnDelete();
            $table->unsignedSmallInteger('category_id')->nullable();
            $table->foreign('category_id', 'fk_tx_category')
                  ->references('id')->on('transaction_categories')->nullOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->string('note', 255)->nullable();
            $table->enum('source', ['manual', 'wa_bot', 'wa_receipt', 'import', 'bill_payment', 'saving_deposit'])->default('manual');
            $table->date('transacted_at');
            $table->char('wa_message_id', 26)->nullable();
            $table->foreign('wa_message_id', 'fk_tx_wa_msg')
                  ->references('id')->on('wa_message_logs')->nullOnDelete();
            $table->char('created_by', 26)->nullable(false);
            $table->foreign('created_by', 'fk_tx_creator')
                  ->references('id')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id', 'idx_tx_user');
            $table->index('wallet_id', 'idx_tx_wallet');
        });

        Schema::create('transaction_edit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('transaction_id', 26)->nullable(false);
            $table->foreign('transaction_id', 'fk_tel_tx')
                  ->references('id')->on('transactions')->cascadeOnDelete();
            $table->char('edited_by', 26)->nullable(false);
            $table->foreign('edited_by', 'fk_tel_editor')
                  ->references('id')->on('users')->restrictOnDelete();
            $table->json('old_data');
            $table->json('new_data');
            $table->enum('action', ['create', 'update', 'delete']);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('edited_at')->useCurrent();
            $table->index('transaction_id', 'idx_tel_tx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_edit_logs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('transaction_categories');
        Schema::dropIfExists('wa_message_logs');
    }
};
