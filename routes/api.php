<?php

use App\Http\Controllers\Trading\IndicatorController;
use App\Http\Controllers\TradingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Route::post('execute', [TradingController::class,'executeTrade']);
