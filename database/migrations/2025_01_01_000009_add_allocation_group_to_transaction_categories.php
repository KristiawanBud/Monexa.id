<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom allocation_group ke tabel transaction_categories.
     *
     * Kolom ini digunakan untuk menghitung formula 50/30/20 secara dinamis
     * tanpa bergantung pada hardcode nama kategori di controller.
     *
     * Nilai yang diizinkan:
     *   'needs'   → Kebutuhan (50%)  misal: Makan, Transport, Tagihan
     *   'wants'   → Keinginan (30%)  misal: Hiburan, Jajan, Hobi
     *   'savings' → Tabungan (20%)   misal: Investasi, Tabungan
     *   null      → Tidak dikategorikan / belum diset
     */
    public function up(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->enum('allocation_group', ['needs', 'wants', 'savings'])
                  ->nullable()
                  ->default(null)
                  ->after('sort_order')
                  ->comment('Kelompok alokasi untuk kalkulasi formula 50/30/20');
        });

        // Isi data untuk kategori sistem yang sudah ada
        // Lakukan via DB::table untuk menghindari masalah model cache
        $mappings = [
            // ── NEEDS (Kebutuhan — 50%) ──────────────────────────
            'Makan & Minum'  => 'needs',
            'Belanja Harian' => 'needs',
            'Transport'      => 'needs',
            'Tagihan'        => 'needs',
            'Kesehatan'      => 'needs',
            'Pendidikan'     => 'needs',
            'Cicilan'        => 'needs',

            // ── WANTS (Keinginan — 30%) ───────────────────────────
            'Hiburan'        => 'wants',

            // ── SAVINGS (Tabungan/Investasi — 20%) ───────────────
            'Investasi'      => 'savings',
        ];

        foreach ($mappings as $name => $group) {
            DB::table('transaction_categories')
                ->where('name', $name)
                ->where('is_system', true)
                ->update(['allocation_group' => $group]);
        }
    }

    public function down(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropColumn('allocation_group');
        });
    }
};
