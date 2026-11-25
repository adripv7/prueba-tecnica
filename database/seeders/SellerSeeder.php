<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class SellerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('es_MX');
        $faker->seed(20261125);

        foreach (BranchSeeder::BRANCHES as $branchIndex => $branchData) {
            $branch = Branch::query()->where('name', $branchData['name'])->firstOrFail();

            for ($sellerNumber = 1; $sellerNumber <= 4; $sellerNumber++) {
                $email = sprintf('vendedor.s%02d.%02d@ventaslite.test', $branchIndex + 1, $sellerNumber);
                $generated = User::factory()->make([
                    'email' => $email,
                    'role' => 'vendedor',
                    'branch_id' => $branch->id,
                ]);

                User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $generated->name,
                        'password' => 'password',
                        'role' => 'vendedor',
                        'branch_id' => $branch->id,
                        'email_verified_at' => now(),
                    ],
                );
            }
        }
    }
}
