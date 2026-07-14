<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('display_name');
            $table->string('color')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
