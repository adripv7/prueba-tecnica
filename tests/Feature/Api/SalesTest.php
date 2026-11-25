<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sale_creation_requires_authentication(): void
    {
        $this->postJson('/api/sales', [])->assertUnauthorized();
    }

    public function test_seller_can_register_sale_with_server_calculated_totals_and_stock(): void
    {
        $branch = Branch::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create(['branch_id' => $branch->id]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => '100.00',
            'cost' => '60.00',
            'active' => true,
        ]);
        Inventory::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 10,
        ]);
        Sanctum::actingAs($seller);
        $salesBefore = Sale::query()->count();

        $this->postJson('/api/sales', [
            'branch_id' => $branch->id,
            'customer_name' => 'Cliente de prueba',
            'payment_method' => 'efectivo',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
            ]],
            'total' => '0.02',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Venta registrada correctamente.')
            ->assertJsonPath('sale.customer_name', 'Cliente de prueba')
            ->assertJsonPath('sale.payment_method', 'efectivo')
            ->assertJsonPath('sale.subtotal', '200.00')
            ->assertJsonPath('sale.tax', '32.00')
            ->assertJsonPath('sale.total', '232.00')
            ->assertJsonPath('sale.items.0.unit_price', '100.00')
            ->assertJsonPath('sale.items.0.line_total', '200.00')
            ->assertJsonMissingPath('sale.items.0.unit_cost');

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '100.00',
            'unit_cost' => '60.00',
            'line_total' => '200.00',
        ]);
        $this->assertDatabaseHas('inventories', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 8,
        ]);
        $this->assertSame($salesBefore + 1, Sale::query()->count());
    }

    public function test_sale_is_rejected_when_stock_is_insufficient_without_partial_writes(): void
    {
        $branch = Branch::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create(['branch_id' => $branch->id]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'active' => true,
        ]);
        Inventory::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 1,
        ]);
        Sanctum::actingAs($seller);
        $salesBefore = Sale::query()->count();

        $this->postJson('/api/sales', [
            'branch_id' => $branch->id,
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);

        $this->assertSame($salesBefore, Sale::query()->count());
        $this->assertDatabaseHas('inventories', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 1,
        ]);
    }

    public function test_seller_cannot_register_sale_in_another_branch(): void
    {
        $assignedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        Sanctum::actingAs(User::factory()->create(['branch_id' => $assignedBranch->id]));

        $this->postJson('/api/sales', [
            'branch_id' => $otherBranch->id,
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para vender en esa sucursal.');
    }

    public function test_sale_validates_payment_method_and_product_lines(): void
    {
        $branch = Branch::factory()->create();
        Sanctum::actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $this->postJson('/api/sales', [
            'branch_id' => $branch->id,
            'payment_method' => 'cheque',
            'items' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method', 'items']);
    }
}
