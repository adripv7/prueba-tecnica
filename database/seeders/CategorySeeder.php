<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Abarrotes',
        'Bebidas',
        'Limpieza',
        'Cuidado personal',
        'Lácteos',
        'Botanas',
        'Enlatados',
        'Desechables',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            $generated = Category::factory()->make(['name' => $name]);

            Category::updateOrCreate(
                ['name' => $name],
                ['name' => $generated->name],
            );
        }
    }
}
