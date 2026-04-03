<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@ggcs.com'],
            [
                'name' => 'Super Admin',
                'password' => 'admin123',
                'status' => 'active',
            ]
        );

        $admin->assignRole('Super Admin');
    }
}
