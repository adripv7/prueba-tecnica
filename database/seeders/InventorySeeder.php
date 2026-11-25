<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('es_MX');
        $faker->seed(20261127);
        $branches = Branch::query()->orderBy('id')->get(['id']);
        $products = Product::query()->orderBy('id')->get(['id']);
        $now = now()->format('Y-m-d H:i:s');
        $rows = [];

        foreach ($branches as $branch) {
            foreach ($products as $product) {
                $rows[] = [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'stock' => $faker->numberBetween(5, 100),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('inventories')->upsert(
                $chunk,
                ['branch_id', 'product_id'],
                ['stock', 'updated_at'],
            );
        }
    }
}
