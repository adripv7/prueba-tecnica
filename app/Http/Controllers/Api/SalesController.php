<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSaleRequest;
use App\Http\Resources\Api\SaleResource;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;

class SalesController extends Controller
{
    public function store(StoreSaleRequest $request, SaleService $sales): JsonResponse
    {
        $sale = $sales->register($request->user(), $request->validated());

        return response()->json([
            'message' => 'Venta registrada correctamente.',
            'sale' => (new SaleResource($sale))->resolve($request),
        ], 201);
    }
}
