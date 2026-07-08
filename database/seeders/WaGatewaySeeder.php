<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WaGatewaySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('wa_gateways')->insert([
            [
                'name'              => 'Nomor 1 — CatatCuan Bot',
                'phone_number'      => '628123456789', // Ganti dengan nomor asli
                'fonnte_token'      => 'GANTI_DENGAN_TOKEN_FONNTE_KAMU',
                'fonnte_device_id'  => null,
                'max_users'         => 50,
                'current_users'     => 0,
                'status'            => 'active',
                'status_note'       => null,
                'total_sent_today'  => 0,
                'total_sent_all'    => 0,
                'last_reset_at'     => null,
                'last_used_at'      => null,
                'is_default'        => true,
                'sort_order'        => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);

        $this->command->info('✅ WaGateway seeder selesai. Jangan lupa update nomor dan token Fonnte di tabel wa_gateways!');
    }
}
