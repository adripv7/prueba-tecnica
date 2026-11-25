<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        $faker = fake('es_MX');
        $quantity = $faker->numberBetween(1, 6);
        $unitPrice = $faker->numberBetween(1000, 50000);
        $unitCost = $faker->numberBetween(500, $unitPrice - 1);
        $unitPriceMoney = intdiv($unitPrice, 100).'.'.str_pad((string) ($unitPrice % 100), 2, '0', STR_PAD_LEFT);
        $unitCostMoney = intdiv($unitCost, 100).'.'.str_pad((string) ($unitCost % 100), 2, '0', STR_PAD_LEFT);
        $lineTotal = $quantity * $unitPrice;

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPriceMoney,
            'unit_cost' => $unitCostMoney,
            'line_total' => intdiv($lineTotal, 100).'.'.str_pad((string) ($lineTotal % 100), 2, '0', STR_PAD_LEFT),
        ];
    }
}
