<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->default(0)->after('unit_price');
        });

        DB::table('sale_items')->update([
            'unit_cost' => DB::raw('(SELECT products.cost FROM products WHERE products.id = sale_items.product_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
