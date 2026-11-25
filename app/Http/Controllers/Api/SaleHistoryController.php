<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelSaleRequest;
use App\Http\Resources\Api\SaleResource;
use App\Models\Sale;
use App\Services\SaleCancellationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request);
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:pagada,cancelada'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $sales = Sale::query()
            ->with(['branch:id,name', 'user:id,name'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('folio', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('sold_at', '>=', $date.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('sold_at', '<', Carbon::parse($date)->addDay()->startOfDay()))
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20)
            ->through(fn (Sale $sale): array => $this->summary($sale));

        return response()->json($sales);
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        $this->requirePermission($request);
        $sale->load([
            'branch:id,name',
            'user:id,name',
            'items.product:id,sku,name',
        ]);

        return response()->json(['data' => (new SaleResource($sale))->resolve($request)]);
    }

    public function cancel(
        CancelSaleRequest $request,
        Sale $sale,
        SaleCancellationService $cancellations,
    ): JsonResponse {
        $cancelledSale = $cancellations->cancel(
            $request->user(),
            $sale,
            $request->validated('reason'),
        );

        return response()->json([
            'message' => 'Venta cancelada correctamente.',
            'sale' => (new SaleResource($cancelledSale))->resolve($request),
        ]);
    }

    private function requirePermission(Request $request): void
    {
        abort_unless($request->user()->hasPermissionTo('sales.view_all'), 403, 'No tienes permiso para consultar todas las ventas.');
    }

    private function summary(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'folio' => $sale->folio,
            'sold_at' => $sale->sold_at->toIso8601String(),
            'customer_name' => $sale->customer_name,
            'branch' => ['id' => $sale->branch->id, 'name' => $sale->branch->name],
            'seller' => ['id' => $sale->user->id, 'name' => $sale->user->name],
            'total' => $sale->total,
            'status' => $sale->status,
        ];
    }
}
