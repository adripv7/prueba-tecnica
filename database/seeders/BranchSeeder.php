<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public const BRANCHES = [
        ['name' => 'Sucursal Ciudad de México', 'city' => 'Ciudad de México'],
        ['name' => 'Sucursal Guadalajara', 'city' => 'Guadalajara'],
        ['name' => 'Sucursal Monterrey', 'city' => 'Monterrey'],
        ['name' => 'Sucursal Puebla', 'city' => 'Puebla'],
        ['name' => 'Sucursal Mérida', 'city' => 'Mérida'],
        ['name' => 'Sucursal Tijuana', 'city' => 'Tijuana'],
        ['name' => 'Sucursal Querétaro', 'city' => 'Querétaro'],
    ];

    public function run(): void
    {
        foreach (self::BRANCHES as $branchData) {
            $generated = Branch::factory()->make($branchData);

            Branch::updateOrCreate(
                ['name' => $branchData['name']],
                ['city' => $generated->city],
            );
        }
    }
}
