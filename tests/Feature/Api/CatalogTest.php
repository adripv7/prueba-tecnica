<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalog_endpoints_require_authentication(): void
    {
        $this->getJson('/api/branches')->assertUnauthorized();
        $this->getJson('/api/categories')->assertUnauthorized();
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_authenticated_user_can_load_branches_and_categories(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'Sucursal Centro',
            'city' => 'Ciudad de México',
        ]);
        $category = Category::factory()->create(['name' => 'Herramientas eléctricas']);
        Sanctum::actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $this->getJson('/api/branches')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $branch->id,
                'name' => 'Sucursal Centro',
                'city' => 'Ciudad de México',
            ]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $category->id,
                'name' => 'Herramientas eléctricas',
            ]);
    }

    public function test_product_search_filters_by_sku_or_name_and_hides_inactive_products(): void
    {
        $category = Category::factory()->create(['name' => 'Herramientas eléctricas']);
        $branch = Branch::factory()->create();
        Sanctum::actingAs(User::factory()->create(['branch_id' => $branch->id]));
        $product = Product::factory()->create([
            'sku' => 'FER-001',
            'name' => 'Taladro percutor 800W',
            'category_id' => $category->id,
            'price' => '2450.00',
            'cost' => '1800.00',
            'stock' => 24,
            'active' => true,
        ]);
        Inventory::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 24,
        ]);
        Product::factory()->create([
            'sku' => 'FER-002',
            'name' => 'Producto descontinuado',
            'category_id' => $category->id,
            'active' => false,
        ]);

        $this->getJson('/api/products?search=FER-001&category_id='.$category->id.'&branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.sku', 'FER-001')
            ->assertJsonPath('data.0.name', 'Taladro percutor 800W')
            ->assertJsonPath('data.0.category.name', 'Herramientas eléctricas')
            ->assertJsonPath('data.0.price', '2450.00')
            ->assertJsonPath('data.0.stock', 24)
            ->assertJsonMissingPath('data.0.cost')
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/products?search=percutor&branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_product_catalog_validates_filters_and_caps_page_size(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $branch = Branch::factory()->create();

        $this->getJson('/api/products')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->getJson('/api/products?category_id=999999&branch_id='.$branch->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);

        $this->getJson('/api/products?per_page=101&branch_id='.$branch->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_seller_can_only_list_and_query_their_assigned_branch(): void
    {
        $assignedBranch = Branch::factory()->create(['name' => 'Sucursal asignada']);
        $otherBranch = Branch::factory()->create(['name' => 'Otra sucursal']);
        Sanctum::actingAs(User::factory()->create(['branch_id' => $assignedBranch->id]));

        $this->getJson('/api/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedBranch->id);

        $this->getJson('/api/products?branch_id='.$otherBranch->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes acceso al inventario de esa sucursal.');
    }

    public function test_admin_can_list_and_query_any_branch(): void
    {
        $branch = Branch::factory()->create();
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'branch_id' => null,
        ]));

        $this->getJson('/api/branches')
            ->assertOk()
            ->assertJsonFragment(['id' => $branch->id]);

        $this->getJson('/api/products?branch_id='.$branch->id)
            ->assertOk();
    }
}
