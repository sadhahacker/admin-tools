<?php

namespace App\Console\Commands;

use App\Http\Controllers\Binance\MarketDataController;
use App\Http\Controllers\Binance\TradeController;
use App\Http\Controllers\Trading\SampleTrading;
use App\Http\Controllers\TradingController;
use App\Models\Signals;
use App\Models\TradingPositions;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RunStrategy extends Command
{
    protected $signature = 'strategy:run';
    protected $description = 'Run Machine Learning: Lorentzian Classification Strategy';

    public function handle()
    {
        if (true) {
            $currentTime = Carbon::now();

            $runCount = Cache::increment('trade_command');

            \Log::channel('trade')->info('Execution Time: ' . $currentTime . ' | Run Count: ' . $runCount);
        }

        $symbol       = 'BNBUSDT';
        $timeInterval = '1m';
        $limit        = 1500;

        $settings = $this->tradeValues();

        //changal values;
        $tradeAmount  = $settings['balance'];
        $leverage     = $settings['leverage'];
        $takeProfit   = $settings['tp'];
        $stopLoss     = $settings['sl'];

        // Fetch market data from trading service.
        $tradingService = new SampleTrading();

        $marketData = $tradingService->market([
            'symbol'   => $symbol,
            'limit'    => $limit,
            'interval' => $timeInterval,
            'tp'       => $takeProfit,
            'sl'       => $stopLoss,
        ]);

        // Get the most recent signal from market data.
        $signal = end($marketData);

        $this->addSignalToDB($symbol, $signal);

        $this->tradeIfPossible($leverage, $tradeAmount);
    }

    public function addSignalToDB($symbol, $data)
    {
        if (empty($data)) {
            return;
        }

        // Check if a pending signal already exists for the symbol.
        $hasPending = Signals::where('symbol', $symbol)
            ->where('status', 'pending')
            ->exists();

        if ($data['status'] === 'NEW SIGNAL' && !$hasPending) {
            Signals::create([
                'symbol'      => $symbol,
                'side'        => $data['signal'],
                'open_time'   => Carbon::createFromTimestampMs($data['open_time']),
                'entry_price' => $data['entry_price'],
                'take_profit' => $data['tp'],
                'stop_loss'   => $data['sl'],
                'status'      => 'pending',
                'successful'  => false,
            ]);
        } else if ($data['status'] !== 'NEW SIGNAL') {
            Signals::where('symbol', $symbol)
                ->where('status', 'pending')
                ->update([
                    'status'     => 'completed',
                    'successful' => ($data['status'] ?? '') === 'TP HIT',
                ]);
        }
    }

    public function tradeIfPossible($leverage, $amount)
    {
        // Retrieve pending signals sorted by creation time.
        $signals = Signals::where('status', 'pending')->latest('created_at')->get();

        $tradingController = new TradingController();

        // Cache cutoff time for signal validity.
        $cutoffTime = Carbon::now()->subMinutes(5);

        // Process each pending signal.
        $signals->each(function ($signal) use ($tradingController, $amount, $leverage, $cutoffTime) {
            // Check if the signal is older than 5 minutes and if there are any open trading positions.
            if ($signal->created_at < $cutoffTime) {
                return; // Skip signals older than 5 minutes.
            }

            // price reached increase 0.1 percentage
            $getEntryPrice = $this->getTradeablePrice($signal->symbol,$signal->side,$signal->entry_price);

            $tradeData = [
                'action'   => $signal->side === 'BUY' ? 'long' : 'short',
                'coin'     => $signal->symbol,
                'quantity' => $amount,
                'price'    => $getEntryPrice,
                'tp'       => $signal->take_profit,
                'sl'       => $signal->stop_loss,
                'leverage' => $leverage,
            ];

            $result = $tradingController->executeTrade(new Request($tradeData));

            $responseData = $result->getData(true); // Converts JsonResponse to an associative array.

            if (isset($responseData['message']) && $responseData['message'] === 'Trade executed successfully') {
                TradingPositions::create([
                    'signal_id' => $signal->id,
                    'amount'    => $amount,
                    'status'    => 'pending',
                    'execution_time' => Carbon::now(),
                ]);
            }
        });
    }

    public function tradeValues()
    {
        $availableBalance = (new TradingController())->getUsdtBalance();
        $riskPercentage = 23;
        $profitGoal = 30;
        $takeProfitPercentage = 3;

        $profitGoalAmount = ($availableBalance * $profitGoal) / 100;

        $positionSize = $profitGoalAmount / ($takeProfitPercentage / 100);

        // Step 2: Calculate leverage
        $leverage = ceil($positionSize / $availableBalance); // Round up leverage

        // Step 3: Adjust position size based on rounded leverage
        $adjustedPositionSize = $leverage * $availableBalance;

        return [
            'balance' => $availableBalance,
            'leverage' => $leverage,
            'amount' => round($adjustedPositionSize, 2),
            'tp' => 3,
            'sl' => 2.3,
        ];
    }

    private function getTradeablePrice($symbol, $side, $price)
    {
        $currentPrice = (new MarketDataController())->getCoinPrice($symbol);

        $priceMargin = $currentPrice * 0.001;

        $side = strtoupper($side);

        if ($side === 'BUY' && $price < $currentPrice + $priceMargin) {
            return $currentPrice + $priceMargin;
        }

        if ($side === 'SELL' && $price > $currentPrice - $priceMargin) {
            return $currentPrice - $priceMargin;
        }

        return $price;
    }
}
