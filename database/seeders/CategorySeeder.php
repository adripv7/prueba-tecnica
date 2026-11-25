<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Herramientas eléctricas',
        'Seguridad industrial',
        'Material eléctrico',
        'Plomería e hidráulica',
        'Herramienta manual',
        'Pinturas y acabados',
        'Soldadura y corte',
        'Fijación y tornillería',
    ];

    public const SKU_PREFIXES = [
        'Herramientas eléctricas' => 'HER',
        'Seguridad industrial' => 'SEG',
        'Material eléctrico' => 'ELE',
        'Plomería e hidráulica' => 'PLM',
        'Herramienta manual' => 'MAN',
        'Pinturas y acabados' => 'PIN',
        'Soldadura y corte' => 'SOL',
        'Fijación y tornillería' => 'FIJ',
    ];

    private const LEGACY_NAMES = [
        'Abarrotes' => 'Herramientas eléctricas',
        'Bebidas' => 'Seguridad industrial',
        'Limpieza' => 'Material eléctrico',
        'Cuidado personal' => 'Plomería e hidráulica',
        'Lácteos' => 'Herramienta manual',
        'Botanas' => 'Pinturas y acabados',
        'Enlatados' => 'Soldadura y corte',
        'Desechables' => 'Fijación y tornillería',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::LEGACY_NAMES as $oldName => $newName) {
                $legacy = Category::query()->where('name', $oldName)->first();
                if ($legacy === null) {
                    continue;
                }

                $current = Category::query()->where('name', $newName)->first();
                if ($current !== null && $current->id !== $legacy->id) {
                    Product::query()->where('category_id', $legacy->id)->update(['category_id' => $current->id]);
                    $legacy->delete();
                } else {
                    $legacy->name = $newName;
                    $legacy->save();
                }
            }

            foreach (self::CATEGORIES as $name) {
                $generated = Category::factory()->make(['name' => $name]);

                Category::updateOrCreate(
                    ['name' => $name],
                    ['name' => $generated->name],
                );
            }
        });
    }
}
