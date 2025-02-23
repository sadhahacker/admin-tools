<?php

use App\Http\Controllers\Trading\SampleTrading;
use App\Http\Controllers\TradingController;
use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return view('welcome');
})->where("any",".*");

Route::get('index', [SampleTrading::class,'market']);

Route::get('phpinfo', function () {
    ob_start();
    phpinfo();
    $phpinfo = ob_get_clean();

    return response($phpinfo)->header('Content-Type', 'text/html');
});

Route::get('trade_logs', function () {

    // Define the log file path
    $logFilePath = storage_path('logs/trades.log');

    // Check if the log file exists
    if (!\File::exists($logFilePath)) {
        return response()->json(['error' => 'Log file not found'], 404);
    }

    // Get the query parameter for the number of logs to return, default to 10 if not provided
    $num = request()->query('num', 10);

    // Read the log file and get the last $num lines
    $logLines = \File::lines($logFilePath)->toArray();  // Get all lines
    $logLines = array_filter($logLines, function($line) {
        return !empty(trim($line));  // Only keep non-empty lines
    });

    // Reverse the array to get the most recent logs first and take the last $num lines
    $logLines = array_slice(array_reverse($logLines), 0, $num);

    return response()->json([
        'logs' => $logLines,
        'num' => $num
    ]);
});

Route::post('execute', [TradingController::class,'executeTrade'])->withoutMiddleware(['web']);
