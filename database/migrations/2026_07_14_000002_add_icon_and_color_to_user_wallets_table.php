<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->string('icon', 10)->nullable()->after('display_name');
            $table->enum('color', ['primary', 'success', 'danger', 'warning', 'info'])
                ->nullable()
                ->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
