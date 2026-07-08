<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->char('currency', 3)->default('IDR');
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->boolean('notif_wa_enabled')->default(true);
            $table->boolean('monthly_report_enabled')->default(true);
            $table->tinyInteger('monthly_report_day')->default(1);
            $table->boolean('saham_enabled')->default(false);
            $table->string('app_logo_url')->nullable();
            $table->string('app_name', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['trial', 'monthly', 'yearly']);
            $table->enum('status', ['active', 'expired', 'cancelled']);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('member_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invite_email', 150);
            $table->string('invite_token', 64)->unique()->nullable();
            $table->enum('role', ['view_only', 'can_edit'])->default('view_only');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('user_profiles');
    }
};
