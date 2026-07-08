<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Soft delete dompet — supaya histori transaksi lama tetap utuh
        // walau dompetnya "dihapus" dari sisi user
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->softDeletes();
        });

        // PRIORITAS #5: Upload logo bank — tambah kolom logo_url
        // Kalau logo_url null, frontend fallback ke logo_initial + logo_color
        Schema::table('banks', function (Blueprint $table) {
            $table->string('logo_url', 255)->nullable()->after('logo_initial');
        });
    }

    public function down(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });
    }
};
