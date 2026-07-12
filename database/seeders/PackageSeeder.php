<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Trial 7 Hari',
                'slug' => 'trial',
                'billing_period' => 'trial',
                'price' => 0,
                'duration_days' => 7,
                'features' => ['Semua fitur dasar', 'CuanAI terbatas', 'WA Bot aktif'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Bulanan',
                'slug' => 'monthly',
                'billing_period' => 'monthly',
                'price' => 29000,
                'duration_days' => 30,
                'features' => ['Semua fitur', 'CuanAI unlimited', 'WA Bot aktif', 'Export laporan'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Tahunan',
                'slug' => 'yearly',
                'billing_period' => 'yearly',
                'price' => 290000,
                'duration_days' => 365,
                'features' => ['Semua fitur', 'CuanAI unlimited', 'WA Bot aktif', 'Export laporan', 'Hemat 2 bulan'],
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $pkg) {
            Package::updateOrCreate(['slug' => $pkg['slug']], $pkg);
        }
    }
}
