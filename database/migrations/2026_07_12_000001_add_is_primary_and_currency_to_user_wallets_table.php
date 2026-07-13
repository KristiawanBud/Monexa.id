<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('is_active');
            $table->char('currency', 3)->default('IDR')->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->dropColumn(['is_primary', 'currency']);
        });
    }
};
