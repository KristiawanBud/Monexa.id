<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->boolean('hide_balance')->default(false)->after('saham_enabled');
            $table->decimal('dana_darurat_target', 15, 2)->nullable()->after('hide_balance');
            $table->tinyInteger('dana_darurat_bulan')->default(6)->after('dana_darurat_target');
        });

        Schema::create('user_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_ua_user')
                  ->references('id')->on('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('emoji', 10)->nullable();
            $table->enum('type', ['liquid', 'fixed', 'investment', 'receivable']);
            $table->decimal('value', 15, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('sort_order')->default(99);
            $table->timestamps();
            $table->index('user_id', 'idx_ua_user');
        });

        Schema::create('receipt_scans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_rs_user')
                  ->references('id')->on('users')->cascadeOnDelete();
            $table->string('image_url');
            $table->json('parsed_result')->nullable();
            $table->enum('status', ['pending', 'parsed', 'confirmed', 'failed'])->default('pending');
            $table->char('transaction_id', 26)->nullable();
            $table->foreign('transaction_id', 'fk_rs_tx')
                  ->references('id')->on('transactions')->nullOnDelete();
            $table->string('ai_provider', 30)->default('gemini');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index('user_id', 'idx_rs_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_scans');
        Schema::dropIfExists('user_assets');
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['hide_balance', 'dana_darurat_target', 'dana_darurat_bulan']);
        });
    }
};
