<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleHistoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seller_can_only_view_their_own_history_and_detail(): void
    {
        $branch = Branch::factory()->create();
        $seller = User::factory()->create(['role' => 'vendedor', 'branch_id' => $branch->id]);
        $otherSeller = User::factory()->create(['role' => 'vendedor', 'branch_id' => $branch->id]);
        $ownSale = Sale::factory()->create([
            'user_id' => $seller->id,
            'branch_id' => $branch->id,
        ]);
        $otherSale = Sale::factory()->create([
            'user_id' => $otherSeller->id,
            'branch_id' => $branch->id,
        ]);
        Sanctum::actingAs($seller);

        $this->getJson('/api/sales')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSale->id);
        $this->getJson('/api/sales/'.$ownSale->id)->assertOk();
        $this->getJson('/api/sales/'.$otherSale->id)->assertNotFound();
        $this->patchJson('/api/sales/'.$ownSale->id.'/cancel')->assertForbidden();
    }

    public function test_admin_can_filter_sales_by_customer_status_and_seller(): void
    {
        $branch = Branch::factory()->create();
        $seller = User::factory()->create(['branch_id' => $branch->id]);
        $sale = Sale::factory()->create([
            'folio' => 'VTA-HISTORY-001',
            'user_id' => $seller->id,
            'branch_id' => $branch->id,
            'customer_name' => 'Cliente Historial',
            'status' => 'pagada',
        ]);
        Sale::factory()->create([
            'user_id' => $seller->id,
            'branch_id' => $branch->id,
            'customer_name' => 'Otro cliente',
            'status' => 'cancelada',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->getJson('/api/sales?search=Historial&status=pagada&branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sale->id)
            ->assertJsonPath('data.0.folio', 'VTA-HISTORY-001')
            ->assertJsonPath('data.0.branch.name', $branch->name)
            ->assertJsonPath('data.0.seller.name', $seller->name);

        $this->getJson('/api/sales?seller_id='.$seller->id.'&status=pagada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sale->id);
    }

    public function test_admin_can_view_sale_detail_without_exposing_cost(): void
    {
        $branch = Branch::factory()->create();
        $seller = User::factory()->create(['branch_id' => $branch->id]);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $sale = Sale::factory()->create([
            'user_id' => $seller->id,
            'branch_id' => $branch->id,
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'unit_price' => '100.00',
            'unit_cost' => '60.00',
            'line_total' => '200.00',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->getJson('/api/sales/'.$sale->id)
            ->assertOk()
            ->assertJsonPath('data.folio', $sale->folio)
            ->assertJsonPath('data.items.0.product.sku', $product->sku)
            ->assertJsonPath('data.items.0.unit_price', '100.00')
            ->assertJsonMissingPath('data.items.0.unit_cost');
    }

    public function test_cancellation_restores_stock_and_cannot_be_applied_twice(): void
    {
        $branch = Branch::factory()->create();
        $seller = User::factory()->create(['branch_id' => $branch->id]);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $sale = Sale::factory()->create([
            'user_id' => $seller->id,
            'branch_id' => $branch->id,
            'status' => 'pagada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        Inventory::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 3,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/sales/'.$sale->id.'/cancel', ['reason' => 'Captura duplicada'])
            ->assertOk()
            ->assertJsonPath('message', 'Venta cancelada correctamente.')
            ->assertJsonPath('sale.status', 'cancelada')
            ->assertJsonPath('sale.cancellation_reason', 'Captura duplicada');

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'cancelada',
            'cancelled_by' => $admin->id,
            'cancellation_reason' => 'Captura duplicada',
        ]);
        $this->assertDatabaseHas('inventories', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 5,
        ]);

        $this->patchJson('/api/sales/'.$sale->id.'/cancel')
            ->assertStatus(409);

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 5,
        ]);
    }
}
