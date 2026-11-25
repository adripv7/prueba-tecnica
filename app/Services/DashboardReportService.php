<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardReportService
{
    private const MONTH_LABELS = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    public function dashboard(array $filters): array
    {
        $to = CarbonImmutable::parse($filters['to'] ?? now()->toDateString())->startOfDay();
        $from = CarbonImmutable::parse($filters['from'] ?? $to->subDays(29)->toDateString())->startOfDay();
        $until = $to->addDay();
        $periodDays = (int) $from->diffInDays($until);
        $previousFrom = $from->subDays($periodDays);
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        $current = $this->salesSummary($from, $until, $branchId);
        $previous = $this->salesSummary($previousFrom, $from, $branchId);
        $currentSalesCents = $this->toCents((string) $current->sales_total);
        $previousSalesCents = $this->toCents((string) $previous->sales_total);
        $currentCount = (int) $current->transactions;
        $previousCount = (int) $previous->transactions;

        return [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $branchId,
            ],
            'summary' => [
                'sales_total' => $this->toMoney($currentSalesCents),
                'sales_before_tax' => $this->toMoney($this->toCents((string) $current->subtotal)),
                'tax_collected' => $this->toMoney($this->toCents((string) $current->tax)),
                'transactions' => $currentCount,
                'average_ticket' => $this->toMoney($currentCount > 0 ? intdiv($currentSalesCents + intdiv($currentCount, 2), $currentCount) : 0),
                'previous_period' => [
                    'from' => $previousFrom->toDateString(),
                    'to' => $from->subDay()->toDateString(),
                    'sales_total' => $this->toMoney($previousSalesCents),
                    'transactions' => $previousCount,
                ],
                'sales_change_percent' => $previousSalesCents === 0
                    ? null
                    : round((($currentSalesCents - $previousSalesCents) / $previousSalesCents) * 100, 2),
            ],
            'sales_by_month' => $this->salesByMonth($to, $branchId),
            'top_products' => $this->topProducts($from, $until, $branchId),
            'seller_ranking' => $this->sellerRanking($from, $until, $branchId),
            'branch_category_matrix' => $this->branchCategoryMatrix($from, $until, $branchId),
            'gross_margin_by_category' => $this->grossMarginByCategory($from, $until, $branchId),
        ];
    }

    private function salesSummary(CarbonImmutable $from, CarbonImmutable $until, ?int $branchId): object
    {
        return $this->applySalesWindow(Sale::query(), $from, $until, $branchId)
            ->select([])
            ->selectRaw('COUNT(sales.id) as transactions')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as sales_total')
            ->selectRaw('COALESCE(SUM(sales.subtotal), 0) as subtotal')
            ->selectRaw('COALESCE(SUM(sales.tax), 0) as tax')
            ->first();
    }

    private function salesByMonth(CarbonImmutable $to, ?int $branchId): array
    {
        $firstMonth = $to->startOfMonth()->subMonths(11);
        $until = $to->addDay();
        $rows = $this->applySalesWindow(Sale::query(), $firstMonth, $until, $branchId)
            ->select([])
            ->selectRaw("DATE_FORMAT(sales.sold_at, '%Y-%m') as month_key")
            ->selectRaw('COALESCE(SUM(sales.total), 0) as sales_total')
            ->selectRaw('COUNT(sales.id) as transactions')
            ->groupBy('month_key')
            ->get()
            ->keyBy('month_key');

        $months = [];
        for ($index = 0; $index < 12; $index++) {
            $month = $firstMonth->addMonths($index);
            $key = $month->format('Y-m');
            $row = $rows->get($key);

            $months[] = [
                'period' => $key,
                'label' => self::MONTH_LABELS[$month->month].' '.$month->format('y'),
                'sales_total' => $this->toMoney($this->toCents((string) ($row->sales_total ?? '0'))),
                'transactions' => (int) ($row->transactions ?? 0),
            ];
        }

        return $months;
    }

    private function topProducts(CarbonImmutable $from, CarbonImmutable $until, ?int $branchId): array
    {
        $products = $this->applySalesWindow(
            SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id'),
            $from,
            $until,
            $branchId,
        )
            ->select(['products.id as product_id', 'products.sku', 'products.name'])
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as amount')
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as units')
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->get();

        return [
            'by_amount' => $products->sortByDesc(fn ($product) => $this->toCents((string) $product->amount))
                ->take(10)->values()->map(fn ($product) => $this->productSummary($product))->all(),
            'by_units' => $products->sortByDesc(fn ($product) => (int) $product->units)
                ->take(10)->values()->map(fn ($product) => $this->productSummary($product))->all(),
        ];
    }

    private function productSummary(object $product): array
    {
        return [
            'id' => (int) $product->product_id,
            'sku' => $product->sku,
            'name' => $product->name,
            'amount' => $this->toMoney($this->toCents((string) $product->amount)),
            'units' => (int) $product->units,
        ];
    }

    private function sellerRanking(CarbonImmutable $from, CarbonImmutable $until, ?int $branchId): array
    {
        return $this->applySalesWindow(
            Sale::query()
                ->join('users', 'users.id', '=', 'sales.user_id')
                ->join('branches', 'branches.id', '=', 'sales.branch_id'),
            $from,
            $until,
            $branchId,
        )
            ->select([
                'users.id as seller_id',
                'users.name as seller_name',
                'branches.id as branch_id',
                'branches.name as branch_name',
                'branches.city as branch_city',
            ])
            ->selectRaw('COUNT(sales.id) as transactions')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as sales_total')
            ->selectRaw('COALESCE(AVG(sales.total), 0) as average_ticket')
            ->groupBy('users.id', 'users.name', 'branches.id', 'branches.name', 'branches.city')
            ->orderByDesc('sales_total')
            ->get()
            ->map(fn ($row): array => [
                'seller' => ['id' => (int) $row->seller_id, 'name' => $row->seller_name],
                'branch' => ['id' => (int) $row->branch_id, 'name' => $row->branch_name, 'city' => $row->branch_city],
                'transactions' => (int) $row->transactions,
                'sales_total' => $this->toMoney($this->toCents((string) $row->sales_total)),
                'average_ticket' => $this->toMoney($this->toCents((string) $row->average_ticket)),
            ])->all();
    }

    private function branchCategoryMatrix(CarbonImmutable $from, CarbonImmutable $until, ?int $branchId): array
    {
        $branchesQuery = Branch::query()->orderBy('name');
        if ($branchId !== null) {
            $branchesQuery->whereKey($branchId);
        }
        $branches = $branchesQuery->get(['id', 'name', 'city']);
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $amounts = [];
        $categoryTotals = [];

        foreach ($branches as $branch) {
            $amounts[$branch->id] = [];
            foreach ($categories as $category) {
                $amounts[$branch->id][$category->id] = 0;
            }
        }
        foreach ($categories as $category) {
            $categoryTotals[$category->id] = 0;
        }

        $aggregate = $this->applySalesWindow(
            SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id'),
            $from,
            $until,
            $branchId,
        )
            ->select(['sales.branch_id', 'products.category_id'])
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as amount')
            ->groupBy('sales.branch_id', 'products.category_id')
            ->get();

        foreach ($aggregate as $row) {
            if (isset($amounts[$row->branch_id][$row->category_id])) {
                $cents = $this->toCents((string) $row->amount);
                $amounts[$row->branch_id][$row->category_id] = $cents;
                $categoryTotals[$row->category_id] += $cents;
            }
        }

        $rows = [];
        $grandTotal = 0;
        foreach ($branches as $branch) {
            $branchTotal = array_sum($amounts[$branch->id]);
            $grandTotal += $branchTotal;
            $rows[] = [
                'branch' => ['id' => $branch->id, 'name' => $branch->name, 'city' => $branch->city],
                'categories' => $categories->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'amount' => $this->toMoney($amounts[$branch->id][$category->id]),
                ])->all(),
                'total' => $this->toMoney($branchTotal),
            ];
        }

        return [
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'total' => $this->toMoney($categoryTotals[$category->id]),
            ])->all(),
            'branches' => $rows,
            'total' => $this->toMoney($grandTotal),
        ];
    }

    private function grossMarginByCategory(CarbonImmutable $from, CarbonImmutable $until, ?int $branchId): array
    {
        $aggregate = $this->applySalesWindow(
            SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id'),
            $from,
            $until,
            $branchId,
        )
            ->select('products.category_id')
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as units')
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(sale_items.unit_cost * sale_items.quantity), 0) as cost')
            ->groupBy('products.category_id')
            ->get()
            ->keyBy('category_id');

        return Category::query()->orderBy('name')->get(['id', 'name'])
            ->map(function (Category $category) use ($aggregate): array {
                $row = $aggregate->get($category->id);
                $revenueCents = $this->toCents((string) ($row->revenue ?? '0'));
                $costCents = $this->toCents((string) ($row->cost ?? '0'));
                $profitCents = $revenueCents - $costCents;

                return [
                    'category' => ['id' => $category->id, 'name' => $category->name],
                    'units' => (int) ($row->units ?? 0),
                    'revenue' => $this->toMoney($revenueCents),
                    'cost' => $this->toMoney($costCents),
                    'gross_profit' => $this->toMoney($profitCents),
                    'margin_percent' => $revenueCents > 0 ? round(($profitCents / $revenueCents) * 100, 1) : 0.0,
                ];
            })->all();
    }

    private function applySalesWindow(
        Builder $query,
        CarbonImmutable $from,
        CarbonImmutable $until,
        ?int $branchId,
    ): Builder {
        $query->where('sales.status', 'pagada')
            ->where('sales.sold_at', '>=', $from)
            ->where('sales.sold_at', '<', $until);

        if ($branchId !== null) {
            $query->where('sales.branch_id', $branchId);
        }

        return $query;
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0', STR_PAD_RIGHT);
    }

    private function toMoney(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
