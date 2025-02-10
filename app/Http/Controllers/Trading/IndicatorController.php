<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Binance\MarketDataController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IndicatorController extends Controller
{
    public $marketData;

    public function __construct()
    {
        $this->marketData = new MarketDataController();
    }

    private function calculateATR(array $data, int $period): float
    {
        $highs = array_column($data, 2);
        $lows = array_column($data, 3);
        $closes = array_column($data, 4);

        $atr = trader_atr($highs, $lows, $closes, $period);
        return $atr ? end($atr) : 0;
    }

    private function calculateRSI(array $data, int $period): float
    {
        $closes = array_column($data, 4);
        $rsi = trader_rsi($closes, $period);
        return $rsi ? end($rsi) : 50; // Default to neutral RSI if not enough data
    }

    function exponentialMovingAverage(array $numbers, int $n): array
    {
        return trader_ema($numbers, $n) ?: [];
    }

    private function calculateAlphaTrend(array $data, float $atrMultiplier, int $period, bool $useRSI = false): array
    {
        $atr = $this->calculateATR($data, $period);
        if ($atr == 0) return [];

        $alphaTrend = [];

        foreach ($data as $index => $candle) {
            $high = floatval($candle[2] ?? 0);
            $low = floatval($candle[3] ?? 0);

            $upT = $low - ($atr * $atrMultiplier);
            $downT = $high + ($atr * $atrMultiplier);

            $trendCheck = $useRSI ? $this->calculateRSI($data, $period) >= 50 : true;

            if ($trendCheck) {
                $alphaTrend[$index] = isset($alphaTrend[$index - 1]) ? min($alphaTrend[$index - 1], $upT) : $upT;
            } else {
                $alphaTrend[$index] = isset($alphaTrend[$index - 1]) ? max($alphaTrend[$index - 1], $downT) : $downT;
            }
        }

        return $alphaTrend;
    }

    private function calculateSuperTrend(array $data, float $atrMultiplier, int $atrPeriod): array
    {
        $atr = $this->calculateATR($data, $atrPeriod);
        if ($atr == 0) return [[], []];

        $superTrend = [];
        $direction = [];

        foreach ($data as $index => $candle) {
            $high = floatval($candle[2] ?? 0);
            $low = floatval($candle[3] ?? 0);
            $close = floatval($candle[4] ?? 0);

            $median = ($high + $low) / 2;
            $upperBand = $median + $atr * $atrMultiplier;
            $lowerBand = $median - $atr * $atrMultiplier;

            if ($index > 0) {
                if ($close > $superTrend[$index - 1]) {
                    $superTrend[$index] = min($upperBand, $superTrend[$index - 1]);
                    $direction[$index] = 1;
                } else {
                    $superTrend[$index] = max($lowerBand, $superTrend[$index - 1]);
                    $direction[$index] = -1;
                }
            } else {
                $superTrend[$index] = $upperBand;
                $direction[$index] = 1;
            }
        }

        return [$superTrend, $direction];
    }

    private function generateSignals(array $alphaTrend, array $superTrend, array $closePrices, float $tpPercentage, float $slPercentage): array
    {
        $buySignals = [];
        $sellSignals = [];
        $buyTP = [];
        $sellTP = [];
        $buySL = [];
        $sellSL = [];
        $executionTime = [];

        for ($i = 2; $i < count($alphaTrend); $i++) {
            // Generate Buy/Sell Signal
            $buySignal = ($alphaTrend[$i] > $alphaTrend[$i - 2]) && ($closePrices[$i] > $superTrend[$i]);
            $sellSignal = ($alphaTrend[$i] < $alphaTrend[$i - 2]) && ($closePrices[$i] < $superTrend[$i]);

            // Capture Execution Time (timestamp)
            $executionTime[] = $buySignal || $sellSignal ? now()->toDateTimeString() : null;

            // Take Profit (TP) and Stop Loss (SL) for Buy Signal
            if ($buySignal) {
                $buySignals[] = $closePrices[$i];
                $buyTP[] = $closePrices[$i] * (1 + $tpPercentage / 100);  // TP calculation
                $buySL[] = $closePrices[$i] * (1 - $slPercentage / 100);  // SL calculation
            } else {
                $buySignals[] = null;
                $buyTP[] = null;
                $buySL[] = null;
            }

            // Take Profit (TP) and Stop Loss (SL) for Sell Signal
            if ($sellSignal) {
                $sellSignals[] = $closePrices[$i];
                $sellTP[] = $closePrices[$i] * (1 - $tpPercentage / 100);  // TP calculation
                $sellSL[] = $closePrices[$i] * (1 + $slPercentage / 100);  // SL calculation
            } else {
                $sellSignals[] = null;
                $sellTP[] = null;
                $sellSL[] = null;
            }
        }

        // Remove null values
        return [
            array_filter($buySignals),
            array_filter($sellSignals),
            array_filter($buyTP),
            array_filter($sellTP),
            array_filter($buySL),
            array_filter($sellSL),
            $executionTime
        ];
    }


    public function getTradingSignals(Request $request)
    {
        $symbol = $request->get('symbol', 'BTCUSDT');
        $interval = $request->get('interval', '1m');
        $atrMultiplier = floatval($request->get('atrMultiplier', 1));
        $atrPeriod = intval($request->get('atrPeriod', 14));
        $useRSI = filter_var($request->get('useRSI', false), FILTER_VALIDATE_BOOLEAN);

        // Fetch Binance Data
        $data = $this->marketData->fetchBinanceData($symbol, $interval);

        if (!$data || count($data) < $atrPeriod) {
            return response()->json(["error" => "Failed to fetch valid data"], 500);
        }

        $alphaTrend = $this->calculateAlphaTrend($data, $atrMultiplier, $atrPeriod, $useRSI);
        list($superTrend, $direction) = $this->calculateSuperTrend($data, 3.0, 10);

        $closePrices = array_map('floatval', array_column($data, 4));
        list($buySignals, $sellSignals) = $this->generateSignals($alphaTrend, $superTrend, $closePrices);

        return response()->json([
            "symbol" => $symbol,
            "interval" => $interval,
            "buy_signals" => $buySignals,
            "sell_signals" => $sellSignals,
        ]);
    }
}
