<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ventaslite.test'],
            [
                'name' => 'Administrador',
                'password' => 'password',
                'role' => 'admin',
                'branch_id' => null,
                'email_verified_at' => now(),
            ],
        );
    }
}
