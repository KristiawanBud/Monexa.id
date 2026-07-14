<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
