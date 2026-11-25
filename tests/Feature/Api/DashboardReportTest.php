<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_reports_are_admin_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'vendedor']));

        $this->getJson('/api/reports/dashboard')->assertForbidden();
        $this->get('/api/reports/dashboard/export?section=sales_by_month')->assertForbidden();
    }

    public function test_admin_dashboard_aggregates_paid_sales_and_returns_all_report_sections(): void
    {
        $branch = Branch::factory()->create();
        $seller = User::factory()->create(['branch_id' => $branch->id]);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Producto para reporte',
            'sku' => 'RPT-001',
        ]);
        $soldAt = CarbonImmutable::parse('2037-02-10 10:30:00');
        Carbon::setTestNow($soldAt);
        $paidSale = Sale::factory()->create([
            'user_id' => $seller->id,
            'branch_id' => $branch->id,
            'customer_name' => 'Cliente reporte',
            'subtotal' => '200.00',
            'tax' => '32.00',
            'total' => '232.00',
            'status' => 'pagada',
            'sold_at' => $soldAt,
        ]);
        SaleItem::factory()->create([
            'sale_id' => $paidSale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '100.00',
            'unit_cost' => '60.00',
            'line_total' => '200.00',
        ]);

        $cancelledSale = Sale::factory()->create([
            'user_id' => $seller->id,
            'branch_id' => $branch->id,
            'subtotal' => '900.00',
            'tax' => '144.00',
            'total' => '1044.00',
            'status' => 'cancelada',
            'sold_at' => $soldAt,
        ]);
        SaleItem::factory()->create([
            'sale_id' => $cancelledSale->id,
            'product_id' => $product->id,
            'quantity' => 9,
            'unit_price' => '100.00',
            'unit_cost' => '60.00',
            'line_total' => '900.00',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $response = $this->getJson('/api/reports/dashboard?from=2037-02-10&to=2037-02-10&branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonPath('data.summary.sales_total', '232.00')
            ->assertJsonPath('data.summary.sales_before_tax', '200.00')
            ->assertJsonPath('data.summary.tax_collected', '32.00')
            ->assertJsonPath('data.summary.transactions', 1)
            ->assertJsonPath('data.summary.average_ticket', '232.00')
            ->assertJsonPath('data.summary.previous_period.sales_total', '0.00')
            ->assertJsonPath('data.summary.sales_change_percent', null)
            ->assertJsonCount(12, 'data.sales_by_month')
            ->assertJsonPath('data.sales_by_month.11.sales_total', '232.00')
            ->assertJsonPath('data.top_products.by_amount.0.sku', 'RPT-001')
            ->assertJsonPath('data.top_products.by_amount.0.amount', '200.00')
            ->assertJsonPath('data.top_products.by_units.0.units', 2)
            ->assertJsonPath('data.seller_ranking.0.transactions', 1)
            ->assertJsonPath('data.seller_ranking.0.sales_total', '232.00');

        $branchRow = collect($response->json('data.branch_category_matrix.branches'))
            ->firstWhere('branch.id', $branch->id);
        $categoryCell = collect($branchRow['categories'])->firstWhere('id', $category->id);
        $this->assertSame('200.00', $categoryCell['amount']);

        $marginRow = collect($response->json('data.gross_margin_by_category'))
            ->firstWhere('category.id', $category->id);
        $this->assertSame('200.00', $marginRow['revenue']);
        $this->assertSame('120.00', $marginRow['cost']);
        $this->assertSame('80.00', $marginRow['gross_profit']);
        $this->assertEquals(40.0, $marginRow['margin_percent']);

        $csv = $this->get('/api/reports/dashboard/export?from=2037-02-10&to=2037-02-10&branch_id='.$branch->id.'&section=top_products&top_products_by=amount')
            ->assertOk()
            ->assertDownload('ventaslite-top_products-2037-02-10-2037-02-10.csv');

        $this->assertStringContainsString('RPT-001', $csv->streamedContent());
        $this->assertStringContainsString('Producto para reporte', $csv->streamedContent());

        foreach ([
            'sales_by_month' => 'Periodo,Mes',
            'seller_ranking' => 'Vendedor,Sucursal',
            'branch_category_matrix' => 'Sucursal,Ciudad',
            'gross_margin_by_category' => 'Categoría,Unidades',
        ] as $section => $expectedHeader) {
            $sectionCsv = $this->get('/api/reports/dashboard/export?from=2037-02-10&to=2037-02-10&branch_id='.$branch->id.'&section='.$section)
                ->assertOk();

            $this->assertStringContainsString($expectedHeader, $sectionCsv->streamedContent());
        }

        $this->getJson('/api/reports/dashboard/export?section=unknown')->assertUnprocessable();
    }
}
