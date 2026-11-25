<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'application' => 'VentasLite API',
        'status' => 'ok',
    ]);
});
