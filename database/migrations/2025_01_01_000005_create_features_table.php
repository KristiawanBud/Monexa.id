<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_sg_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('emoji', 10)->nullable();
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->date('deadline')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('user_id', 'idx_sg_user');
        });

        Schema::create('saving_deposits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('saving_goal_id', 26)->nullable(false);
            $table->foreign('saving_goal_id', 'fk_sd_goal')
                ->references('id')->on('saving_goals')->cascadeOnDelete();
            $table->char('wallet_id', 26)->nullable(false);
            $table->foreign('wallet_id', 'fk_sd_wallet')
                ->references('id')->on('user_wallets')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('deposited_at');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_bills_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('emoji', 10)->nullable();
            $table->enum('type', ['recurring', 'one_time'])->default('recurring');
            $table->decimal('amount', 15, 2);
            $table->tinyInteger('due_day')->nullable();
            $table->date('due_date')->nullable();
            $table->json('remind_days')->nullable();
            $table->boolean('notif_wa_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('user_id', 'idx_bills_user');
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('bill_id', 26)->nullable(false);
            $table->foreign('bill_id', 'fk_bp_bill')
                ->references('id')->on('bills')->cascadeOnDelete();
            $table->char('wallet_id', 26)->nullable(false);
            $table->foreign('wallet_id', 'fk_bp_wallet')
                ->references('id')->on('user_wallets')->restrictOnDelete();
            $table->char('transaction_id', 26)->nullable();
            $table->foreign('transaction_id', 'fk_bp_tx')
                ->references('id')->on('transactions')->nullOnDelete();
            $table->decimal('amount_paid', 15, 2);
            $table->date('paid_at');
            $table->char('for_period', 7);
            $table->enum('source', ['manual', 'wa_bot'])->default('manual');
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_budgets_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('category_id');
            $table->foreign('category_id', 'fk_budgets_cat')
                ->references('id')->on('transaction_categories')->cascadeOnDelete();
            $table->char('period', 7);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['user_id', 'category_id', 'period'], 'uq_budget');
        });

        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_ms_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->char('period', 7);
            $table->decimal('total_income', 15, 2)->default(0);
            $table->decimal('total_expense', 15, 2)->default(0);
            $table->decimal('total_saving', 15, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->unique(['user_id', 'period'], 'uq_ms_user_period');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_notif_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 100);
            $table->text('body')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('wa_sent')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index('user_id', 'idx_notif_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('monthly_summaries');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('saving_deposits');
        Schema::dropIfExists('saving_goals');
    }
};
