<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Kristiawan Budiono',
            'email' => 'admin@catatcuan.id',
            'password' => Hash::make('admin123'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'yearly',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYears(10),
        ]);
    }
}
