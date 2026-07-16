<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id')->nullable()->after('note');
            $table->foreign('category_id', 'fk_wt_category')
                ->references('id')->on('transaction_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->dropForeign('fk_wt_category');
            $table->dropColumn('category_id');
        });
    }
};
