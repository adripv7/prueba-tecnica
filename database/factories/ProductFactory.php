<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $faker = fake('es_MX');
        $costCents = $faker->numberBetween(5000, 350000);
        $priceCents = $costCents + $faker->numberBetween(500, max(500, intdiv($costCents, 2)));

        return [
            'sku' => 'SKU-'.Str::upper($faker->unique()->bothify('????-#####')),
            'name' => ucfirst($faker->words(3, true)),
            'category_id' => Category::factory(),
            'price' => $this->toMoney($priceCents),
            'cost' => $this->toMoney($costCents),
            'stock' => $faker->numberBetween(0, 200),
            'active' => $faker->boolean(90),
        ];
    }

    private function toMoney(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
