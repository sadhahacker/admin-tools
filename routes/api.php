<?php

use App\Http\Controllers\Admin\SignalsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('getSignals',[SignalsController::class,'getSignals']);
Route::get('getTrades',[SignalsController::class,'getTrades']);
