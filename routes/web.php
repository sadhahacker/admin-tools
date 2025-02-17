<?php

use App\Http\Controllers\Trading\SampleTrading;
use App\Http\Controllers\TradingController;
use Illuminate\Support\Facades\Route;

//Route::get('/{any}', function () {
//    return view('welcome');
//})->where("any",".*");

Route::get('index', [SampleTrading::class,'market']);

Route::get('phpinfo', function () {
    ob_start();
    phpinfo();
    $phpinfo = ob_get_clean();

    return response($phpinfo)->header('Content-Type', 'text/html');
});


Route::post('execute', [TradingController::class,'executeTrade'])->withoutMiddleware(['web']);
