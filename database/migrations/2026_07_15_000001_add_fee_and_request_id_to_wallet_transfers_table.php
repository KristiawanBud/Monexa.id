<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->decimal('fee', 15, 2)->default(0)->after('amount');
            $table->string('request_id', 64)->nullable()->after('note');

            $table->unique(['user_id', 'request_id'], 'uq_wt_user_request');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->dropUnique('uq_wt_user_request');
            $table->dropColumn(['fee', 'request_id']);
        });
    }
};
