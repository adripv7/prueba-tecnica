<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaleCancellationService
{
    public function cancel(User $actor, Sale $sale, ?string $reason): Sale
    {
        return DB::transaction(function () use ($actor, $sale, $reason): Sale {
            $sale = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($sale->status === 'cancelada', 409, 'La venta ya fue cancelada.');

            $items = $sale->items()
                ->orderBy('product_id')
                ->get(['id', 'product_id', 'quantity']);
            $productIds = $items->pluck('product_id')->unique()->sort()->values();
            $inventories = Inventory::query()
                ->where('branch_id', $sale->branch_id)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            if ($inventories->count() !== $productIds->count()) {
                abort(409, 'No se pudo reponer el inventario de la venta.');
            }

            foreach ($items as $item) {
                $inventories->get($item->product_id)->increment('stock', $item->quantity);
            }

            $sale->forceFill([
                'status' => 'cancelada',
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            return $sale->load([
                'branch:id,name',
                'user:id,name',
                'items.product:id,sku,name',
            ]);
        }, attempts: 3);
    }
}
