<?php

namespace App\Console\Commands;

use App\Http\Controllers\Binance\MarketDataController;
use App\Http\Controllers\Trading\SampleTrading;
use App\Http\Controllers\TradingController;
use App\Models\Signals;
use App\Models\TradingPositions;
use Carbon\Carbon;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class RunStrategy extends Command
{
    protected $signature = 'strategy:run';

    protected $description = 'Run Machine Learning: Lorentzian Classification Strategy';

    public function handle()
    {
        $symbol = 'BNBUSDT';
        $timeInterval = '5m';
        $limit = 500;

        // Fetch market data
        $trading = new SampleTrading();
        $data = $trading->market([
            'symbol' => $symbol,
            'limit' => $limit,
            'interval' => $timeInterval,
            'tp' => 3,
            'sl' => 3,
        ]);

        if (empty($data) || !is_array($data)) {
            return dd("No data available");
        }

        // Get the last trading signal
        $lastSignal = Signals::where('symbol', $symbol)->latest()->first();

        // Get the latest market data point
        $latestData = end($data);

        // Ensure latest data is valid
        if (!isset($latestData['signal'], $latestData['entry_price'], $latestData['open_time'], $latestData['tp'], $latestData['sl'])) {
            return dd("Invalid market data received");
        }

        $newSignalSide = $latestData['signal'] === 'BUY' ? 'LONG' : 'SHORT';
        $newEntryPrice = (string)$latestData['entry_price'];
        $openTime = Carbon::createFromTimestampMs($latestData['open_time']);

        // If no previous signal, insert a new one
        if (!$lastSignal) {
            Signals::create([
                'symbol' => $symbol,
                'side' => $newSignalSide,
                'open_time' => $openTime,
                'entry_price' => $newEntryPrice,
                'take_profit' => $latestData['tp'],
                'stop_loss' => $latestData['sl'],
                'status' => 'pending',
                'successful' => false
            ]);
        } // Insert a new signal if there's a "NEW SIGNAL" status and entry price matches
        elseif ($latestData['status'] === "NEW SIGNAL" && (string)$lastSignal->entry_price === $newEntryPrice) {
            Signals::create([
                'symbol' => $symbol,
                'side' => $newSignalSide,
                'open_time' => $openTime,
                'entry_price' => $newEntryPrice,
                'take_profit' => $latestData['tp'],
                'stop_loss' => $latestData['sl'],
                'status' => 'pending',
                'successful' => false
            ]);
        } // Update the last signal status if take profit (TP) is hit
        elseif ($lastSignal) {
            $isSuccess = $latestData['status'] === 'TP HIT';

            Signals::where('entry_price', $lastSignal->entry_price)
                ->update([
                    'status' => 'completed',
                    'successful' => $isSuccess
                ]);
        }

        // Fetch all pending trading signals
        $tradingSignals = Signals::where('status', 'pending')->latest()->get();

        $tradeableSignal = null;
        $currentTime = Carbon::now();

        foreach ($tradingSignals as $signal) {
            // Ensure open_time and created_at are parsed as Carbon instances
            $openTime = Carbon::parse($signal->open_time);
            $createdAt = Carbon::parse($signal->created_at);

            $openTimeValid = $openTime->lt($currentTime->copy()->subMinutes(5));
            $createdTimeValid = $createdAt->addMinutes(5)->lt($currentTime);

            if ($openTimeValid && $createdTimeValid) {
                if (!$tradeableSignal || Carbon::parse($tradeableSignal->open_time)->lt($openTime)) {
                    $tradeableSignal = $signal;
                }
            }
        }

        dd($tradeableSignal);
    }
}
