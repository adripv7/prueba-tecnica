<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    private const PRODUCT_NAMES = [
        'Herramientas eléctricas' => [
            'Taladro percutor industrial 800W', 'Esmeriladora angular 4 1/2 pulgadas', 'Rotomartillo SDS Plus',
            'Sierra circular para madera 7 1/4 pulgadas', 'Atornillador eléctrico de impacto', 'Lijadora orbital 5 pulgadas',
            'Pulidora para acabado automotriz', 'Multiherramienta oscilante', 'Compresor portátil de aire',
            'Sierra caladora de velocidad variable',
        ],
        'Seguridad industrial' => [
            'Casco de seguridad industrial blanco', 'Botas dieléctricas con casquillo de poliamida',
            'Lentes de seguridad antiempañantes', 'Guantes de carnaza reforzados', 'Chaleco reflejante alta visibilidad',
            'Protección auditiva tipo copa', 'Arnés de seguridad de cuerpo completo', 'Respirador de media cara',
            'Careta para esmerilar', 'Faja de soporte lumbar industrial',
        ],
        'Material eléctrico' => [
            'Cable uso rudo 3x12 rollo 100m', 'Extensión eléctrica uso rudo 15m', 'Centro de carga 8 circuitos',
            'Interruptor termomagnético 2 polos', 'Contacto dúplex polarizado', 'Lámpara LED industrial 100W',
            'Tubo conduit galvanizado 3/4 pulgada', 'Cinta aislante profesional 18m', 'Clavija industrial aterrizada',
            'Caja rectangular galvanizada',
        ],
        'Plomería e hidráulica' => [
            'Bomba centrífuga 1 HP', 'Válvula de esfera de latón 1 pulgada', 'Tubo PVC hidráulico 1 pulgada 6m',
            'Conexión codo PVC hidráulico 90 grados', 'Mezcladora para fregadero cromada', 'Llave de jardín de latón',
            'Tinaco tricapa 1100 litros', 'Bomba presurizadora doméstica', 'Cinta teflón para tubería',
            'Conector flexible para lavabo',
        ],
        'Herramienta manual' => [
            'Juego de llaves mixtas 14 piezas', 'Pinza de presión 10 pulgadas', 'Martillo de uña con mango fibra',
            'Desarmador punta plana aislado', 'Juego de dados cuadro 1/2 pulgada', 'Llave ajustable cromada 12 pulgadas',
            'Arco para segueta profesional', 'Cinta métrica reforzada 8m', 'Prensa tipo C 4 pulgadas',
            'Juego de pinzas para electricista',
        ],
        'Pinturas y acabados' => [
            'Cubeta pintura vinílica blanca 19L', 'Esmalte anticorrosivo negro 4L', 'Sellador acrílico para muro 19L',
            'Impermeabilizante elastomérico rojo 19L', 'Brocha profesional 4 pulgadas', 'Rodillo para pintura 9 pulgadas',
            'Lija para madera grano 120 paquete', 'Espátula flexible 4 pulgadas', 'Thinner estándar 1L',
            'Charola para pintura mediana',
        ],
        'Soldadura y corte' => [
            'Inversora para soldar 160A bi-voltaje', 'Electrodo revestido 6013 1/8 pulgada',
            'Careta electrónica para soldar', 'Alambre MIG calibre 0.030 rollo', 'Soplete para corte oxiacetilénico',
            'Pinza para tierra de soldadora 300A', 'Portaelectrodo reforzado 300A', 'Disco de corte metal 4 1/2 pulgadas',
            'Regulador para cilindro de oxígeno', 'Cepillo de alambre para soldadura',
        ],
        'Fijación y tornillería' => [
            'Caja taquete expansivo 3/8 pulgada 100 piezas', 'Tornillo para madera cabeza plana 2 pulgadas',
            'Pija galvanizada para lámina 1 pulgada', 'Taquete plástico con tornillo 1/4 pulgada',
            'Varilla roscada galvanizada 3/8 pulgada', 'Tuerca hexagonal acero inoxidable 3/8 pulgada',
            'Rondana plana galvanizada 1/2 pulgada', 'Ancla tipo mariposa para tablaroca',
            'Perno de anclaje expansivo 1/2 pulgada', 'Caja de clavo para concreto 2 pulgadas',
        ],
    ];

    public function run(): void
    {
        $faker = fake('es_MX');
        $faker->seed(20261124);
        $categoryIdsByName = Category::query()
            ->whereIn('name', CategorySeeder::CATEGORIES)
            ->pluck('id', 'name');
        $categoryIds = array_map(fn (string $name) => $categoryIdsByName->get($name), CategorySeeder::CATEGORIES);

        if (count($categoryIds) !== 8 || in_array(null, $categoryIds, true)) {
            throw new \RuntimeException('ProductSeeder requiere primero las 8 categorías.');
        }

        $now = now()->format('Y-m-d H:i:s');
        $products = [];

        for ($number = 1; $number <= 150; $number++) {
            $categoryName = CategorySeeder::CATEGORIES[($number - 1) % count(CategorySeeder::CATEGORIES)];
            $categoryIndex = ($number - 1) % count(CategorySeeder::CATEGORIES);
            $categoryPrefix = CategorySeeder::SKU_PREFIXES[$categoryName];
            $sku = strtoupper($faker->unique()->bothify($categoryPrefix.'-??-####'));
            $productName = $faker->randomElement(self::PRODUCT_NAMES[$categoryName])
                .' modelo '.strtoupper($faker->unique()->bothify('??-###'));
            $costCents = $faker->numberBetween(5000, 350000);
            $priceCents = $costCents + $faker->numberBetween(500, max(500, intdiv($costCents, 2)));
            $product = Product::factory()->make([
                'sku' => $sku,
                'name' => $productName,
                'category_id' => $categoryIds[$categoryIndex],
                'cost' => $this->toMoney($costCents),
                'price' => $this->toMoney($priceCents),
                'stock' => $faker->numberBetween(10, 200),
                'active' => $number % 10 !== 0,
            ]);

            Product::query()->where('sku', sprintf('SKU-%04d', $number))->update(['sku' => $sku]);

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
