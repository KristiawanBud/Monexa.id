<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            // Konvensional
            ['name' => 'Bank Central Asia',        'short_name' => 'BCA',        'type' => 'conventional', 'logo_color' => '#003399', 'logo_initial' => 'B', 'sort_order' => 1],
            ['name' => 'Bank Mandiri',              'short_name' => 'Mandiri',    'type' => 'conventional', 'logo_color' => '#003d8f', 'logo_initial' => 'M', 'sort_order' => 2],
            ['name' => 'Bank Negara Indonesia',     'short_name' => 'BNI',        'type' => 'conventional', 'logo_color' => '#FF6600', 'logo_initial' => 'N', 'sort_order' => 3],
            ['name' => 'Bank Rakyat Indonesia',     'short_name' => 'BRI',        'type' => 'conventional', 'logo_color' => '#00529B', 'logo_initial' => 'R', 'sort_order' => 4],
            ['name' => 'CIMB Niaga',                'short_name' => 'CIMB',       'type' => 'conventional', 'logo_color' => '#CC0001', 'logo_initial' => 'C', 'sort_order' => 5],
            ['name' => 'Bank Danamon',              'short_name' => 'Danamon',    'type' => 'conventional', 'logo_color' => '#D01F2F', 'logo_initial' => 'D', 'sort_order' => 6],
            ['name' => 'Permata Bank',              'short_name' => 'Permata',    'type' => 'conventional', 'logo_color' => '#0080C6', 'logo_initial' => 'P', 'sort_order' => 7],
            ['name' => 'Maybank Indonesia',         'short_name' => 'Maybank',    'type' => 'conventional', 'logo_color' => '#E31837', 'logo_initial' => 'M', 'sort_order' => 8],
            ['name' => 'BTN',                       'short_name' => 'BTN',        'type' => 'conventional', 'logo_color' => '#FF6600', 'logo_initial' => 'T', 'sort_order' => 9],
            // Syariah
            ['name' => 'Bank Syariah Indonesia',    'short_name' => 'BSI',        'type' => 'syariah',      'logo_color' => '#00A550', 'logo_initial' => 'S', 'sort_order' => 10],
            ['name' => 'Bank Muamalat',             'short_name' => 'Muamalat',   'type' => 'syariah',      'logo_color' => '#006B3F', 'logo_initial' => 'M', 'sort_order' => 11],
            // Digital
            ['name' => 'SeaBank Indonesia',         'short_name' => 'SeaBank',    'type' => 'digital',      'logo_color' => '#CC0001', 'logo_initial' => 'S', 'sort_order' => 20],
            ['name' => 'PT Bank Jago Tbk',          'short_name' => 'Bank Jago',  'type' => 'digital',      'logo_color' => '#00C37B', 'logo_initial' => 'J', 'sort_order' => 21],
            ['name' => 'BCA Digital (Blu)',          'short_name' => 'Blu BCA',    'type' => 'digital',      'logo_color' => '#003399', 'logo_initial' => 'B', 'sort_order' => 22],
            ['name' => 'Allo Bank Indonesia',        'short_name' => 'Allo Bank',  'type' => 'digital',      'logo_color' => '#FF6E00', 'logo_initial' => 'A', 'sort_order' => 23],
            ['name' => 'Bank Neo Commerce',          'short_name' => 'Neo',        'type' => 'digital',      'logo_color' => '#7B2FBE', 'logo_initial' => 'N', 'sort_order' => 24],
            ['name' => 'Bank Raya Indonesia',        'short_name' => 'Raya',       'type' => 'digital',      'logo_color' => '#0080FF', 'logo_initial' => 'R', 'sort_order' => 25],
            ['name' => 'Superbank',                  'short_name' => 'Superbank',  'type' => 'digital',      'logo_color' => '#1A1A1A', 'logo_initial' => 'S', 'sort_order' => 26],
        ];

        DB::table('banks')->insert($banks);
    }
}
