<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Kolom allocation_group ditambahkan setelah migration
        // 2025_01_01_000009_add_allocation_group_to_transaction_categories.php
        // dijalankan. Seeder ini mengisi nilai tersebut langsung
        // sehingga sesuai untuk fresh seed maupun re-seed.
        $categories = [
            // ── INCOME ───────────────────────────────────────────
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Gaji',
                'emoji'            => '💼',
                'is_system'        => true,
                'sort_order'       => 1,
                'allocation_group' => null,   // income tidak masuk 50/30/20
            ],
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Bonus',
                'emoji'            => '💰',
                'is_system'        => true,
                'sort_order'       => 2,
                'allocation_group' => null,
            ],
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Investasi',
                'emoji'            => '📈',
                'is_system'        => true,
                'sort_order'       => 3,
                'allocation_group' => null,
            ],
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Hadiah',
                'emoji'            => '🎁',
                'is_system'        => true,
                'sort_order'       => 4,
                'allocation_group' => null,
            ],
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Sewa',
                'emoji'            => '🏠',
                'is_system'        => true,
                'sort_order'       => 5,
                'allocation_group' => null,
            ],
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Freelance',
                'emoji'            => '🤝',
                'is_system'        => true,
                'sort_order'       => 6,
                'allocation_group' => null,
            ],
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Jualan',
                'emoji'            => '📦',
                'is_system'        => true,
                'sort_order'       => 7,
                'allocation_group' => null,
            ],
            [
                'user_id'          => null,
                'type'             => 'income',
                'name'             => 'Lainnya',
                'emoji'            => '✨',
                'is_system'        => true,
                'sort_order'       => 99,
                'allocation_group' => null,
            ],

            // ── EXPENSE — NEEDS (Kebutuhan 50%) ──────────────────
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Makan & Minum',
                'emoji'            => '🍜',
                'is_system'        => true,
                'sort_order'       => 1,
                'allocation_group' => 'needs',
            ],
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Belanja Harian',
                'emoji'            => '🛒',
                'is_system'        => true,
                'sort_order'       => 2,
                'allocation_group' => 'needs',
            ],
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Transport',
                'emoji'            => '⛽',
                'is_system'        => true,
                'sort_order'       => 3,
                'allocation_group' => 'needs',
            ],
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Tagihan',
                'emoji'            => '💡',
                'is_system'        => true,
                'sort_order'       => 4,
                'allocation_group' => 'needs',
            ],
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Kesehatan',
                'emoji'            => '🏥',
                'is_system'        => true,
                'sort_order'       => 5,
                'allocation_group' => 'needs',
            ],
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Pendidikan',
                'emoji'            => '📚',
                'is_system'        => true,
                'sort_order'       => 6,
                'allocation_group' => 'needs',
            ],
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Cicilan',
                'emoji'            => '💳',
                'is_system'        => true,
                'sort_order'       => 8,
                'allocation_group' => 'needs',
            ],

            // ── EXPENSE — WANTS (Keinginan 30%) ──────────────────
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Hiburan',
                'emoji'            => '🎮',
                'is_system'        => true,
                'sort_order'       => 7,
                'allocation_group' => 'wants',
            ],

            // ── EXPENSE — Lainnya (tidak masuk kelompok) ─────────
            [
                'user_id'          => null,
                'type'             => 'expense',
                'name'             => 'Lainnya',
                'emoji'            => '✨',
                'is_system'        => true,
                'sort_order'       => 99,
                'allocation_group' => null,
            ],
        ];

        DB::table('transaction_categories')->insert($categories);
    }
}
