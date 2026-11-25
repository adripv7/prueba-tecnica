<?php

namespace App\Http\Resources\Api;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'customer_name' => $this->customer_name,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ],
            'seller' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'items' => $this->items->map(fn ($item): array => [
                'product' => [
                    'id' => $item->product->id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                ],
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values(),
        ];
    }
}
