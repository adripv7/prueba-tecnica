<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function register(User $user, array $data): Sale
    {
        $branchId = (int) $data['branch_id'];

        abort_unless(
            $user->role === 'admin' || (int) $user->branch_id === $branchId,
            403,
            'No tienes permiso para vender en esa sucursal.',
        );

        return DB::transaction(function () use ($user, $data, $branchId): Sale {
            $lines = $data['items'];
            $productIds = collect($lines)
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            $products = Product::query()
                ->whereKey($productIds)
                ->where('active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o más productos no están activos.',
                ]);
            }

            $inventories = Inventory::query()
                ->where('branch_id', $branchId)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            if ($inventories->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Falta inventario para uno o más productos en esa sucursal.',
                ]);
            }

            $subtotalCents = 0;
            $saleItems = [];
            $stockChanges = [];

            foreach ($lines as $index => $line) {
                $productId = (int) $line['product_id'];
                $quantity = (int) $line['quantity'];
                $product = $products->get($productId);
                $inventory = $inventories->get($productId);

                if ($inventory->stock < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Existencias insuficientes. Disponibles: {$inventory->stock}.",
                    ]);
                }

                $unitPriceCents = $this->toCents((string) $product->price);
                $unitCostCents = $this->toCents((string) $product->cost);
                $lineTotalCents = $unitPriceCents * $quantity;
                $subtotalCents += $lineTotalCents;

                $saleItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $this->toMoney($unitPriceCents),
                    'unit_cost' => $this->toMoney($unitCostCents),
                    'line_total' => $this->toMoney($lineTotalCents),
                ];
                $stockChanges[] = [$inventory, $quantity];
            }

            $taxCents = intdiv(($subtotalCents * 16) + 50, 100);
            if ($subtotalCents + $taxCents > 999_999_999_999) {
                throw ValidationException::withMessages([
                    'items' => 'El total de la venta excede el máximo permitido.',
                ]);
            }

            $sale = Sale::query()->create([
                'folio' => 'VTA-'.Str::upper((string) Str::ulid()),
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'customer_name' => $data['customer_name'] ?? 'Público en General',
                'subtotal' => $this->toMoney($subtotalCents),
                'tax' => $this->toMoney($taxCents),
                'total' => $this->toMoney($subtotalCents + $taxCents),
                'payment_method' => $data['payment_method'],
                'status' => 'pagada',
                'sold_at' => now(),
            ]);

            $sale->items()->createMany($saleItems);

            foreach ($stockChanges as [$inventory, $quantity]) {
                $inventory->decrement('stock', $quantity);
            }

            return $sale->load([
                'branch:id,name',
                'user:id,name',
                'items.product:id,sku,name',
            ]);
        }, attempts: 3);
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
