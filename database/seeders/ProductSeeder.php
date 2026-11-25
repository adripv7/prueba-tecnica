<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('es_MX');
        $faker->seed(20261124);
        $categoryIdsByName = Category::query()
            ->whereIn('name', CategorySeeder::CATEGORIES)
            ->pluck('id', 'name');
        $categoryIds = array_map(
            fn (string $name) => $categoryIdsByName->get($name),
            CategorySeeder::CATEGORIES,
        );

        if (count($categoryIds) !== 8 || in_array(null, $categoryIds, true)) {
            throw new \RuntimeException('ProductSeeder requiere primero las 8 categorías.');
        }

        $now = now()->format('Y-m-d H:i:s');
        $products = [];

        for ($number = 1; $number <= 150; $number++) {
            $costCents = $faker->numberBetween(5000, 350000);
            $priceCents = $costCents + $faker->numberBetween(500, max(500, intdiv($costCents, 2)));
            $product = Product::factory()->make([
                'sku' => sprintf('SKU-%04d', $number),
                'name' => ucfirst($faker->words(3, true)),
                'category_id' => $categoryIds[($number - 1) % count($categoryIds)],
                'cost' => $this->toMoney($costCents),
                'price' => $this->toMoney($priceCents),
                'stock' => $faker->numberBetween(10, 200),
                'active' => $number % 10 !== 0,
            ]);

            $products[] = [
                'sku' => $product->sku,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price' => $product->price,
                'cost' => $product->cost,
                'stock' => $product->stock,
                'active' => $product->active,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($products, 500) as $chunk) {
            DB::table('products')->upsert(
                $chunk,
                ['sku'],
                ['name', 'category_id', 'price', 'cost', 'stock', 'active', 'updated_at'],
            );
        }
    }

    private function toMoney(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
