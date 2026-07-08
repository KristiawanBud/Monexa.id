<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Migration ini sengaja dikosongkan.
     *
     * Kolom monitoring (last_ping_at, is_connected, disconnected_at,
     * owner_wa_number) dan tabel system_settings sudah dibuat langsung
     * di migration 2025_01_01_000006_create_wa_gateways_table.php
     * sehingga tidak perlu duplikasi di sini.
     *
     * File ini tetap disimpan (bukan dihapus) supaya urutan nomor
     * migration tidak berubah dan riwayat tetap konsisten.
     */
    public function up(): void
    {
        // Sengaja kosong — lihat keterangan di atas.
    }

    public function down(): void
    {
        // Sengaja kosong — lihat keterangan di atas.
    }
};
