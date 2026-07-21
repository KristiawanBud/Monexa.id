<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_gateways', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 80);
            $table->string('phone_number', 25)->unique();
            $table->string('fonnte_token', 255);
            $table->string('fonnte_device_id', 100)->nullable();
            $table->unsignedSmallInteger('max_users')->default(50);
            $table->unsignedSmallInteger('current_users')->default(0);
            $table->enum('status', ['active', 'warning', 'suspended', 'inactive'])->default('active');
            $table->string('status_note', 255)->nullable();
            $table->unsignedInteger('total_sent_today')->default(0);
            $table->unsignedInteger('total_sent_all')->default(0);
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->boolean('is_connected')->default(true);
            $table->timestamp('disconnected_at')->nullable();
            $table->string('owner_wa_number', 25)->nullable();
            $table->boolean('is_default')->default(false);
            $table->tinyInteger('sort_order')->default(99);
            $table->timestamps();
        });

        Schema::create('user_wa_gateways', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_uwg_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('gateway_id');
            $table->foreign('gateway_id', 'fk_uwg_gateway')
                ->references('id')->on('wa_gateways')->cascadeOnDelete();
            $table->enum('status', ['active', 'released'])->default('active');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 100)->nullable();
        });

        Schema::create('wa_gateway_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('gateway_id');
            $table->foreign('gateway_id', 'fk_wgl_gateway')
                ->references('id')->on('wa_gateways')->cascadeOnDelete();
            $table->char('user_id', 26)->nullable();
            $table->foreign('user_id', 'fk_wgl_user')
                ->references('id')->on('users')->nullOnDelete();
            $table->string('to_number', 25);
            $table->enum('type', ['transaction', 'bill_reminder', 'monthly_report', 'export', 'system', 'test']);
            $table->enum('status', ['sent', 'failed', 'queued']);
            $table->string('error_message', 255)->nullable();
            $table->timestamp('sent_at')->useCurrent();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_gateway_logs');
        Schema::dropIfExists('user_wa_gateways');
        Schema::dropIfExists('wa_gateways');
        Schema::dropIfExists('system_settings');
    }
};
