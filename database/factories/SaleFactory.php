<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $faker = fake('es_MX');

        return [
            'folio' => 'VTA-'.Str::upper(Str::random(12)),
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'customer_name' => $faker->name(),
            'subtotal' => '0.00',
            'tax' => '0.00',
            'total' => '0.00',
            'payment_method' => $faker->randomElement(['efectivo', 'tarjeta', 'transferencia']),
            'status' => 'pagada',
            'sold_at' => $faker->dateTimeBetween('-18 months', 'now'),
        ];
    }
}
