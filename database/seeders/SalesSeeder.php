<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesSeeder extends Seeder
{
    private const SALES_COUNT = 2500;

    public function run(): void
    {
        $faker = fake('es_MX');
        $faker->seed(20261126);
        $branchNames = array_column(BranchSeeder::BRANCHES, 'name');
        $branches = Branch::query()->whereIn('name', $branchNames)->get()->keyBy('name');
        $orderedBranches = collect($branchNames)->map(fn (string $name) => $branches->get($name));
        $regularBranches = $orderedBranches->take(6)->values();
        $lowVolumeBranch = $orderedBranches->last();
        $branchIds = $orderedBranches->pluck('id')->all();
        $sellersByBranch = User::query()
            ->where('role', 'vendedor')
            ->whereIn('branch_id', $branchIds)
            ->orderBy('email')
            ->get()
            ->groupBy('branch_id');
        $products = Product::query()->where('active', true)->orderBy('id')->get();

        if ($orderedBranches->contains(null) || $regularBranches->count() !== 6 || $products->count() < 6) {
            throw new RuntimeException('SalesSeeder requiere 7 sucursales y al menos 6 productos activos.');
        }

        foreach ($orderedBranches as $branch) {
            if (($sellersByBranch->get($branch->id)?->count() ?? 0) !== 4) {
                throw new RuntimeException("SalesSeeder requiere 4 vendedores en {$branch->name}.");
            }
        }

        $months = $this->weightedMonths();
        $saleRows = [];
        $saleFolios = [];
        $itemRows = [];
        $regularBranchIndex = 0;
        $now = now()->format('Y-m-d H:i:s');

        for ($number = 1; $number <= self::SALES_COUNT; $number++) {
            $isLowVolumeBranch = $number % 20 === 0;
            $branch = $isLowVolumeBranch
                ? $lowVolumeBranch
                : $regularBranches[$regularBranchIndex++ % $regularBranches->count()];
            $sellers = $sellersByBranch->get($branch->id)->values();
            $seller = $sellers[($number - 1) % $sellers->count()];
            $itemCount = match ($number % 20) {
                0 => 1,
                1 => 2,
                2 => 6,
                default => 3,
            };
            $selectedProducts = collect($faker->randomElements($products->all(), $itemCount, false));
            $folio = sprintf('SEED-%06d', $number);
            $subtotalCents = 0;

            foreach ($selectedProducts as $product) {
                $quantity = $faker->numberBetween(1, 5);
                $unitPriceCents = $this->toCents((string) $product->price);
                $lineTotalCents = $unitPriceCents * $quantity;
                $subtotalCents += $lineTotalCents;

                $item = SaleItem::factory()->make([
                    'sale_id' => 1,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $this->toMoney($unitPriceCents),
                    'unit_cost' => $product->cost,
                    'line_total' => $this->toMoney($lineTotalCents),
                ]);

                $itemRows[] = [
                    'folio' => $folio,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $item->line_total,
                ];
            }

            $taxCents = intdiv(($subtotalCents * 16) + 50, 100);
            $soldAt = $this->randomDateFrom($months, $faker);
            $isCancelled = $number % 20 === 7;
            $sale = Sale::factory()->make([
                'folio' => $folio,
                'user_id' => $seller->id,
                'branch_id' => $branch->id,
                'customer_name' => $faker->name(),
                'subtotal' => $this->toMoney($subtotalCents),
                'tax' => $this->toMoney($taxCents),
                'total' => $this->toMoney($subtotalCents + $taxCents),
                'payment_method' => $faker->randomElement(['efectivo', 'tarjeta', 'transferencia']),
                'status' => $isCancelled ? 'cancelada' : 'pagada',
                'cancelled_at' => $isCancelled ? $soldAt : null,
                'cancelled_by' => $isCancelled ? $seller->id : null,
                'cancellation_reason' => $isCancelled ? 'Cancelación de prueba' : null,
                'sold_at' => $soldAt,
            ]);

            $saleRows[] = [
                'folio' => $sale->folio,
                'user_id' => $sale->user_id,
                'branch_id' => $sale->branch_id,
                'customer_name' => $sale->customer_name,
                'subtotal' => $sale->subtotal,
                'tax' => $sale->tax,
                'total' => $sale->total,
                'payment_method' => $sale->payment_method,
                'status' => $sale->status,
                'cancelled_at' => $sale->cancelled_at?->format('Y-m-d H:i:s'),
                'cancelled_by' => $sale->cancelled_by,
                'cancellation_reason' => $sale->cancellation_reason,
                'sold_at' => $sale->sold_at->format('Y-m-d H:i:s'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $saleFolios[] = $folio;
        }

        DB::transaction(function () use ($saleRows, $saleFolios, $itemRows, $now): void {
            DB::table('sales')->where('folio', 'like', 'SEED-%')->delete();

            foreach (array_chunk($saleRows, 500) as $chunk) {
                DB::table('sales')->insert($chunk);
            }

            $saleIds = collect();
            foreach (array_chunk($saleFolios, 500) as $folioChunk) {
                $saleIds = $saleIds->merge(
                    DB::table('sales')->whereIn('folio', $folioChunk)->pluck('id', 'folio'),
                );
            }

            $rows = [];
            foreach ($itemRows as $item) {
                $rows[] = [
                    'sale_id' => $saleIds->get($item['folio']),
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['line_total'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('sale_items')->insert($chunk);
            }
        });
    }

    /** @return list<Carbon> */
    private function weightedMonths(): array
    {
        $now = now();
        $start = $now->copy()->subMonths(18);
        $month = $start->copy()->startOfMonth();
        $months = [];

        while ($month->lte($now)) {
            $weight = $month->month === 12 ? 3 : 1;

            for ($copy = 0; $copy < $weight; $copy++) {
                $months[] = $month->copy();
            }

            $month->addMonth();
        }

        return $months;
    }

    private function randomDateFrom(array $months, object $faker): string
    {
        $now = now();
        $start = $now->copy()->subMonths(18);
        $month = $faker->randomElement($months)->copy();
        $from = $month->greaterThan($start) ? $month : $start;
        $monthEnd = $month->copy()->endOfMonth();
        $until = $monthEnd->lessThan($now) ? $monthEnd : $now;

        return $faker->dateTimeBetween($from->toDateTimeString(), $until->toDateTimeString())->format('Y-m-d H:i:s');
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0', STR_PAD_RIGHT);
    }

    private function toMoney(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
