<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function branches(Request $request): JsonResponse
    {
        $user = $request->user();
        $branches = Branch::query();

        if ($user->role !== 'admin') {
            if (! $user->branch_id) {
                return response()->json([
                    'message' => 'El usuario no tiene una sucursal asignada.',
                ], 403);
            }

            $branches->whereKey($user->branch_id);
        }

        return response()->json([
            'data' => $branches
                ->orderBy('name')
                ->get(['id', 'name', 'city']),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        if ($user->role !== 'admin' && (int) $user->branch_id !== (int) $filters['branch_id']) {
            return response()->json([
                'message' => 'No tienes acceso al inventario de esa sucursal.',
            ], 403);
        }

        $products = Product::query()
            ->where('active', true)
            ->whereHas('inventories', fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->with([
                'category:id,name',
                'inventories' => fn (HasMany $query) => $query
                    ->where('branch_id', $filters['branch_id'])
                    ->select(['id', 'branch_id', 'product_id', 'stock']),
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20)
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ],
                'price' => $product->price,
                'stock' => $product->inventories->first()?->stock ?? 0,
            ]);

        return response()->json($products);
    }
}
