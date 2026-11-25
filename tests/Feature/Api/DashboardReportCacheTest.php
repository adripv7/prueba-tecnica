<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardReportCache;
use App\Services\DashboardReportService;
use App\Services\SaleCancellationService;
use App\Services\SaleService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardReportCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_report_cache_is_scoped_to_filters_and_can_be_invalidated(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $cache = app(DashboardReportCache::class);
        $from = CarbonImmutable::parse('2026-11-01');
        $to = CarbonImmutable::parse('2026-11-25');
        $calls = 0;
        $calculate = function () use (&$calls): array {
            $calls++;

            return ['calculation' => $calls];
        };

        $this->assertSame(['calculation' => 1], $cache->remember($from, $to, null, $calculate));
        $this->assertSame(['calculation' => 1], $cache->remember($from, $to, null, $calculate));
        $this->assertSame(['calculation' => 2], $cache->remember($from, $to, 7, $calculate));

        $cache->invalidate();

        $this->assertSame(['calculation' => 3], $cache->remember($from, $to, null, $calculate));
    }

    public function test_registering_and_cancelling_sales_refreshes_dashboard_metrics(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-12 12:00:00'));

        $branch = Branch::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create(['branch_id' => $branch->id]);
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);
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

        $reports = app(DashboardReportService::class);
        $filters = ['from' => '2026-11-01', 'to' => '2026-11-25', 'branch_id' => $branch->id];
        $this->assertSame('0.00', $reports->dashboard($filters)['summary']['sales_total']);

        $sale = app(SaleService::class)->register($seller, [
            'branch_id' => $branch->id,
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
        $this->assertSame('232.00', $reports->dashboard($filters)['summary']['sales_total']);

        app(SaleCancellationService::class)->cancel($admin, $sale, 'Prueba de invalidación');
        $this->assertSame('0.00', $reports->dashboard($filters)['summary']['sales_total']);
    }
}
