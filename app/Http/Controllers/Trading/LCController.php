<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Binance\MarketDataController;
use App\Http\Controllers\Controller;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;

class LCController extends Controller
{

    public $marketData;

    public $historyData;

    public function __construct(){
        $this->marketData = new MarketDataController();

        $this->historyData = $this->parseBinanceData($this->marketData->fetchBinanceData('ETHUSDT','3m'));
    }
    /**
     * Compute EMA using the Trader extension (or fallback to SMA if needed).
     */
    private function computeEma(array $data, int $period): array
    {
        if (extension_loaded('trader')) {
            $ema = trader_ema($data, $period);
            return $ema !== false ? array_values($ema) : $data;
        }
        return $this->computeSma($data, $period);
    }

    /**
     * Compute SMA using the Trader extension.
     */
    private function computeSma(array $data, int $period): array
    {
        if (extension_loaded('trader')) {
            $sma = trader_sma($data, $period);
            return $sma !== false ? array_values($sma) : $data;
        }
        $len = count($data);
        $sma = [];
        for ($i = 0; $i < $len; $i++) {
            if ($i < $period - 1) {
                $sma[] = null;
            } else {
                $slice = array_slice($data, $i - $period + 1, $period);
                $sma[] = array_sum($slice) / $period;
            }
        }
        return $sma;
    }

    /**
     * Compute RSI using the Trader extension.
     */
    private function computeRsi(array $data, int $period = 14): array
    {
        if (extension_loaded('trader')) {
            $rsi = trader_rsi($data, $period);
            return $rsi !== false ? array_values($rsi) : array_fill(0, count($data), 50);
        }
        return array_fill(0, count($data), 50);
    }

    /**
     * Dummy function to compute a Lorentzian distance.
     */
    private function getLorentzianDistance(int $i, int $featureCount): float
    {
        return abs($i) * 1.0;
    }

    /**
     * Simulated feature series calculation.
     * (In your real code, this might compute RSI, CCI, etc.)
     * Here we simply multiply the close price by a parameter.
     */
    private function seriesFrom(string $feature, array $close, int $paramA, int $paramB): array
    {
        return array_map(function ($price) use ($paramA) {
            return $price * $paramA;
        }, $close);
    }

    private function parseBinanceData($binanceData)
    {
        return array_map(function ($candle) {
            return [
                'timestamp' => (int)$candle[0], // Changed to integer
                'open' => (float)$candle[1],
                'high' => (float)$candle[2],
                'low' => (float)$candle[3],
                'close' => (float)$candle[4],
                'volume' => (float)$candle[5]
            ];
        }, $binanceData);
    }

    /**
     * Main endpoint that produces the prediction and simulates a full trade timeline.
     */
    public function predict(Request $request)
    {


        // -------------------------------------
        // 1. Price Data Initialization
        // -------------------------------------
        $close = array_column($this->historyData, 'close');
        $high  = array_column($this->historyData, 'high');
        $low   = array_column($this->historyData, 'low');

        // Calculate hlc3: (Close + High + Low) / 3
        $hlc3 = array_map(function ($c, $h, $l) {
            return ($c + $h + $l) / 3;
        }, $close, $high, $low);

        // -------------------------------------
        // 2. Settings & Filter Settings
        // -------------------------------------
        $settings = (object)[
            'source'           => $close,
            'neighborsCount'   => 8,
            'maxBarsBack'      => 2000,
            'featureCount'     => 5,
            'colorCompression' => 1,
            'showDefaultExits' => false,
            'useDynamicExits'  => false,
        ];

        $filterSettings = (object)[
            'useVolatilityFilter' => true,
            'useRegimeFilter'     => true,
            'useAdxFilter'        => false,
            'regimeThreshold'     => -0.1,
            'adxThreshold'        => 20,
        ];

        // Dummy filter flags
        $filter = (object)[
            'volatility' => true,
            'regime'     => true,
            'adx'        => true,
        ];

        // -------------------------------------
        // 3. Define Label/Direction
        // -------------------------------------
        $direction = (object)[
            'long'    => 1,
            'short'   => -1,
            'neutral' => 0,
        ];

        // -------------------------------------
        // 4. Feature Settings & Series
        // -------------------------------------
        $f1_settings = (object)['title' => "Feature 1", 'defval' => "RSI"];
        $f1_paramA = 14; $f1_paramB = 1;
        $f2_settings = (object)['title' => "Feature 2", 'defval' => "WT"];
        $f2_paramA = 10; $f2_paramB = 11;
        $f3_settings = (object)['title' => "Feature 3", 'defval' => "CCI"];
        $f3_paramA = 20; $f3_paramB = 1;
        $f4_settings = (object)['title' => "Feature 4", 'defval' => "ADX"];
        $f4_paramA = 20; $f4_paramB = 2;
        $f5_settings = (object)['title' => "Feature 5", 'defval' => "RSI"];
        $f5_paramA = 9;  $f5_paramB = 1;

        $featureSeries = (object)[
            'f1' => $this->seriesFrom($f1_settings->defval, $close, $f1_paramA, $f1_paramB),
            'f2' => $this->seriesFrom($f2_settings->defval, $close, $f2_paramA, $f2_paramB),
            'f3' => $this->seriesFrom($f3_settings->defval, $close, $f3_paramA, $f3_paramB),
            'f4' => $this->seriesFrom($f4_settings->defval, $close, $f4_paramA, $f4_paramB),
            'f5' => $this->seriesFrom($f5_settings->defval, $close, $f5_paramA, $f5_paramB),
        ];
        $featureArrays = $featureSeries;

        // -------------------------------------
        // 5. Derived Index and EMA/SMA Filters
        // -------------------------------------
        $last_bar_index = count($close) - 1;
        $max_bars_back_index = ($last_bar_index >= $settings->maxBarsBack) ? $last_bar_index : 0;

        // EMA Filter using Trader extension
        $use_ema_filter = true;
        $ema_period = 200;
        $ema_values = $use_ema_filter ? $this->computeEma($close, $ema_period) : $close;
        $is_ema_uptrend = [];
        $is_ema_downtrend = [];
        foreach ($close as $i => $price) {
            if ($use_ema_filter && isset($ema_values[$i]) && $ema_values[$i] !== null) {
                $is_ema_uptrend[] = ($price > $ema_values[$i]);
                $is_ema_downtrend[] = ($price < $ema_values[$i]);
            } else {
                $is_ema_uptrend[] = true;
                $is_ema_downtrend[] = true;
            }
        }

        // SMA Filter using Trader extension
        $use_sma_filter = true;
        $sma_period = 200;
        $sma_values = $use_sma_filter ? $this->computeSma($close, $sma_period) : $close;
        $is_sma_uptrend = [];
        $is_sma_downtrend = [];
        foreach ($close as $i => $price) {
            if ($use_sma_filter && isset($sma_values[$i]) && $sma_values[$i] !== null) {
                $is_sma_uptrend[] = ($price > $sma_values[$i]);
                $is_sma_downtrend[] = ($price < $sma_values[$i]);
            } else {
                $is_sma_uptrend[] = true;
                $is_sma_downtrend[] = true;
            }
        }

        // -------------------------------------
        // 6. Kernel Regression Settings (Dummy)
        // -------------------------------------
        $use_kernel_filter = true;
        $show_kernel_estimate = true;
        $use_kernel_smoothing = false;
        $h = 8;      // Lookback Window
        $r = 8.0;    // Relative Weighting
        $x = 25;     // Regression Level
        $lag = 2;    // Lag

        // Dummy kernel regression: here we simply use the close prices.
        $yhat1 = $close;
        $yhat2 = $close;
        $kernel_estimate = $yhat1;

        $currentIndex = count($yhat1) - 1;
        $was_bearish_rate = (isset($yhat1[2]) && isset($yhat1[1])) ? ($yhat1[2] > $yhat1[1]) : false;
        $was_bullish_rate = (isset($yhat1[2]) && isset($yhat1[1])) ? ($yhat1[2] < $yhat1[1]) : false;
        $is_bearish_rate = ($currentIndex > 0) ? ($yhat1[$currentIndex - 1] > $yhat1[$currentIndex]) : false;
        $is_bullish_rate = ($currentIndex > 0) ? ($yhat1[$currentIndex - 1] < $yhat1[$currentIndex]) : false;
        $is_bearish_change = $is_bearish_rate && $was_bullish_rate;
        $is_bullish_change = $is_bullish_rate && $was_bullish_rate;

        $is_bullish_cross_alert = ($currentIndex >= 1 && $yhat2[$currentIndex] > $yhat1[$currentIndex] && $yhat2[$currentIndex - 1] <= $yhat1[$currentIndex - 1]);
        $is_bearish_cross_alert = ($currentIndex >= 1 && $yhat2[$currentIndex] < $yhat1[$currentIndex] && $yhat2[$currentIndex - 1] >= $yhat1[$currentIndex - 1]);
        $is_bullish_smooth = ($yhat2[$currentIndex] >= $yhat1[$currentIndex]);
        $is_bearish_smooth = ($yhat2[$currentIndex] <= $yhat1[$currentIndex]);

        $c_green = '#009988';
        $c_red = '#CC3311';
        $transparent = '#000000';
        $plot_color = $show_kernel_estimate ? ($is_bullish_smooth ? $c_green : $c_red) : $transparent;
        \Log::info("Kernel Estimate Plot Color: " . $plot_color);

        $alert_bullish = $use_kernel_smoothing ? $is_bullish_cross_alert : $is_bullish_change;
        $alert_bearish = $use_kernel_smoothing ? $is_bearish_cross_alert : $is_bearish_change;
        $is_bullish = $use_kernel_filter ? $is_bullish_smooth : true;
        $is_bearish = $use_kernel_filter ? $is_bearish_smooth : true;

        // -------------------------------------
        // 7. Core ML Logic (Prediction)
        // -------------------------------------
        // Build training labels using a 4‑bar lookahead.
        $y_train_array = [];
        $lenSource = count($settings->source);
        for ($i = 0; $i < $lenSource - 4; $i++) {
            if ($settings->source[$i + 4] < $settings->source[$i]) {
                $y_train_array[] = $direction->short;
            } elseif ($settings->source[$i + 4] > $settings->source[$i]) {
                $y_train_array[] = $direction->long;
            } else {
                $y_train_array[] = $direction->neutral;
            }
        }

        $predictions = [];
        $distances = [];
        $last_distance = -1.0;
        $size = min($settings->maxBarsBack - 1, count($y_train_array) - 1);
        $size_loop = $size;

        for ($i = 0; $i <= $size_loop; $i++) {
            $d = $this->getLorentzianDistance($i, $settings->featureCount);
            if ($d >= $last_distance && ($i % 4) != 0) {
                $last_distance = $d;
                $distances[] = $d;
                $predictions[] = round($y_train_array[$i]);
                if (count($predictions) > $settings->neighborsCount) {
                    $index = round($settings->neighborsCount * 3 / 4);
                    $last_distance = isset($distances[$index]) ? $distances[$index] : $last_distance;
                    array_shift($distances);
                    array_shift($predictions);
                }
            }
        }
        $prediction = array_sum($predictions);

        // -------------------------------------
        // 8. Signal Calculation
        // -------------------------------------
        $filter_all = $filter->volatility && $filter->regime && $filter->adx;
        if ($prediction > 0 && $filter_all) {
            $signal = $direction->long;
        } elseif ($prediction < 0 && $filter_all) {
            $signal = $direction->short;
        } else {
            $signal = $direction->neutral;
        }

        // -------------------------------------
        // 9. Entry & Exit Logic (Snapshot)
        // -------------------------------------
        // For the current (final) bar we simulate trade entry/exit signals.
        $bars_held = 0;
        $changeSignal = [$signal]; // In a real backtest, this would be the signal history.
        if ($changeSignal[0]) {
            $bars_held = 0;
        } else {
            $bars_held++;
        }
        $is_held_four_bars = ($bars_held == 4);
        $is_held_less_than_four_bars = ($bars_held > 0 && $bars_held < 4);

        $is_different_signal_type = true;
        $is_new_buy_signal = ($signal === $direction->long) && $is_different_signal_type;
        $is_new_sell_signal = ($signal === $direction->short) && $is_different_signal_type;

        $is_buy_signal = ($signal === $direction->long) && end($is_ema_uptrend) && end($is_sma_uptrend);
        $is_sell_signal = ($signal === $direction->short) && end($is_ema_downtrend) && end($is_sma_downtrend);
        $is_last_signal_buy = ($signal === $direction->long) && $is_ema_uptrend[0] && $is_sma_uptrend[0];
        $is_last_signal_sell = ($signal === $direction->short) && $is_ema_downtrend[0] && $is_sma_downtrend[0];

        $start_long_trade = $is_new_buy_signal && $is_bullish && end($is_ema_uptrend) && end($is_sma_uptrend);
        $start_short_trade = $is_new_sell_signal && $is_bearish && end($is_ema_downtrend) && end($is_sma_downtrend);

        $end_long_trade_dynamic = $is_bearish_change;
        $end_short_trade_dynamic = $is_bullish_change;

        $end_long_trade_strict = (($is_held_four_bars && $is_last_signal_buy) ||
            ($is_held_less_than_four_bars && $is_new_sell_signal && $is_last_signal_buy));
        $end_short_trade_strict = (($is_held_four_bars && $is_last_signal_sell) ||
            ($is_held_less_than_four_bars && $is_new_buy_signal && $is_last_signal_sell));

        $is_dynamic_exit_valid = (!$use_ema_filter && !$use_sma_filter && !$use_kernel_smoothing);
        if ($settings->useDynamicExits && $is_dynamic_exit_valid) {
            $end_long_trade = $end_long_trade_dynamic;
            $end_short_trade = $end_short_trade_dynamic;
        } else {
            $end_long_trade = $end_long_trade_strict;
            $end_short_trade = $end_short_trade_strict;
        }

        // -------------------------------------
        // 10. Build Snapshot Trade Signals (Current Bar)
        // -------------------------------------
        $tradeSignals = [];
        $currentPrice = end($close);
        if ($start_long_trade) {
            $tradeSignals[] = [
                'type'    => 'OPEN_LONG',
                'price'   => $currentPrice,
                'message' => "Open Long Position (Prediction: {$prediction})"
            ];
        }
        if ($start_short_trade) {
            $tradeSignals[] = [
                'type'    => 'OPEN_SHORT',
                'price'   => $currentPrice,
                'message' => "Open Short Position (Prediction: {$prediction})"
            ];
        }
        if ($end_long_trade && $settings->showDefaultExits) {
            $tradeSignals[] = [
                'type'    => 'EXIT_LONG',
                'price'   => $currentPrice,
                'message' => "Close Long Position"
            ];
        }
        if ($end_short_trade && $settings->showDefaultExits) {
            $tradeSignals[] = [
                'type'    => 'EXIT_SHORT',
                'price'   => $currentPrice,
                'message' => "Close Short Position"
            ];
        }

        // -------------------------------------
        // 11. Simulate Full Backtest Timeline with Timestamps
        // -------------------------------------
        // Here we iterate over every bar, assign a timestamp, and simulate trade events.
        // In your real application, you’d compute the signal per bar using your strategy.
        // Inside the predict() method (before the backtest loop)
        $openTrades = [];
        $completedTrades = [];
        $earlySignalFlips = 0;

// -------------------------------------
// 11. Simulate Full Backtest Timeline with Timestamps
// -------------------------------------
        $tradeEvents = [];
        $totalBars = count($close);

        for ($i = 0; $i < $totalBars; $i++) {
            // Get timestamp from historical data
            $timestampMs = $this->historyData[$i]['timestamp'];
            $barTime = (new DateTime())->setTimestamp((int)($timestampMs / 1000));
            $barTime->setTimezone(new DateTimeZone('UTC'));
            $formattedTime = $barTime->format('Y-m-d H:i:s');

            // ------------------------------------------------------
            // Calculate Strategy Signal for This Bar (Dummy Example)
            // ------------------------------------------------------
            // Replace this with your actual strategy logic
            $currentClose = $close[$i];
            $isLongSignal = ($i % 10 === 0);    // Example condition
            $isShortSignal = ($i % 15 === 0);   // Example condition
            $isExitSignal = ($i % 17 === 0);    // Example condition

            // Close Open Trades First
            foreach ($openTrades as $key => $trade) {
                $isDynamicExit = ($isExitSignal || $this->isDynamicExitConditionMet($trade, $currentClose));
                if ($isDynamicExit) {
                    // Calculate P/L
                    $profit = $currentClose - $trade['entryPrice'];
                    $isWin = ($trade['type'] === 'LONG') ? ($profit > 0) : ($profit < 0);

                    $completedTrades[] = [
                        'type'       => $trade['type'],
                        'entryPrice' => $trade['entryPrice'],
                        'exitPrice'  => $currentClose,
                        'profit'     => $profit,
                        'isWin'      => $isWin,
                        'duration'   => $i - $trade['entryIndex']
                    ];

                    $tradeEvents[] = [
                        'time'    => $formattedTime,
                        'type'    => "CLOSE_{$trade['type']}",
                        'price'   => $currentClose,
                        'message' => "Closed {$trade['type']} trade"
                    ];

                    unset($openTrades[$key]);
                }
            }

            // Open New Trades
            if ($isLongSignal && empty($openTrades)) {
                $openTrades[] = [
                    'type'        => 'LONG',
                    'entryPrice'  => $currentClose,
                    'entryIndex'  => $i,
                    'entryTime'   => $formattedTime
                ];

                $tradeEvents[] = [
                    'time'    => $formattedTime,
                    'type'    => 'OPEN_LONG',
                    'price'   => $currentClose,
                    'message' => "Opened Long Position"
                ];
            }

            if ($isShortSignal && empty($openTrades)) {
                $openTrades[] = [
                    'type'        => 'SHORT',
                    'entryPrice'  => $currentClose,
                    'entryIndex'  => $i,
                    'entryTime'   => $formattedTime
                ];

                $tradeEvents[] = [
                    'time'    => $formattedTime,
                    'type'    => 'OPEN_SHORT',
                    'price'   => $currentClose,
                    'message' => "Opened Short Position"
                ];
            }

            // Track Early Signal Flips (Optional)
            if (count($openTrades) > 0 && ($isLongSignal || $isShortSignal)) {
                $earlySignalFlips++;
            }
        }


        // For demonstration, we set the backTestStream equal to the simulated trade events.
        $backTestStream = $tradeEvents;

        // -------------------------------------
        // 12. Dummy Trade Stats (Calibration)
        // -------------------------------------
        // -------------------------------------
// 12. Dynamic Trade Stats Calculation
// -------------------------------------
        $showTradeStats = true;
        $tradeStats = null;

        if ($showTradeStats && !empty($completedTrades)) {
            $totalWins = 0;
            $totalLosses = 0;
            $totalProfit = 0;

            foreach ($completedTrades as $trade) {
                if ($trade['isWin']) {
                    $totalWins++;
                } else {
                    $totalLosses++;
                }
                $totalProfit += $trade['profit'];
            }

            $totalTrades = count($completedTrades);
            $winLossRatio = $totalLosses > 0 ? $totalWins / $totalLosses : "N/A";
            $winRate = $totalTrades > 0 ? ($totalWins / $totalTrades) * 100 : 0;

            $tradeStats = [
                'totalTrades'       => $totalTrades,
                'winRate'           => number_format($winRate, 1) . '%',
                'winLossRatio'      => is_numeric($winLossRatio) ? number_format($winLossRatio, 2) : $winLossRatio,
                'totalProfit'       => number_format($totalProfit, 2),
                'avgTradeDuration'  => number_format(
                        array_sum(array_column($completedTrades, 'duration')) / $totalTrades,
                        1
                    ) . ' bars',
                'earlySignalFlips' => $earlySignalFlips
            ];
        } else {
            $tradeStats = null;
        }

        // -------------------------------------
        // 13. Build and Return the JSON Response
        // -------------------------------------
        $response = [
            'prediction'     => $prediction,
            'signal'         => $signal,
            'tradeSignals'   => $tradeSignals,      // Snapshot of current signals
            'backTestStream' => $backTestStream,    // Full list of simulated trades with timestamps
            'tradeStats'     => $tradeStats,
        ];

        return response()->json($response);
    }
    /**
     * Check if dynamic exit conditions are met
     */
    private function isDynamicExitConditionMet(array $trade, float $currentPrice): bool
    {
        // Example: 2% trailing stop
        $threshold = $trade['entryPrice'] * 0.02;

        if ($trade['type'] === 'LONG') {
            return ($currentPrice < $trade['entryPrice'] - $threshold);
        }

        if ($trade['type'] === 'SHORT') {
            return ($currentPrice > $trade['entryPrice'] + $threshold);
        }

        return false;
    }

    public function emaCrossoverStrategy()
    {
        $close = array_column($this->historyData, 'close');
        $timestamps = array_column($this->historyData, 'timestamp');

        // Calculate EMAs
        $ema50 = $this->computeEma($close, 50);
        $ema200 = $this->computeEma($close, 200);

        $signals = [];
        $previousPosition = null;

        for ($i = 1; $i < count($close); $i++) {
            // Skip if we don't have enough EMA data
            if (!isset($ema50[$i]) || !isset($ema200[$i]) ||
                !isset($ema50[$i-1]) || !isset($ema200[$i-1])) {
                continue;
            }

            $currentEma50 = $ema50[$i];
            $currentEma200 = $ema200[$i];
            $prevEma50 = $ema50[$i-1];
            $prevEma200 = $ema200[$i-1];

            // Convert timestamp to datetime
            $timestampMs = $timestamps[$i];
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int)($timestampMs / 1000));
            $dateTime->setTimezone(new DateTimeZone('UTC'));
            $formattedTime = $dateTime->format('Y-m-d H:i:s');

            // Detect crossover
            $signal = null;
            if ($prevEma50 < $prevEma200 && $currentEma50 > $currentEma200) {
                $signal = 'BUY';
            } elseif ($prevEma50 > $prevEma200 && $currentEma50 < $currentEma200) {
                $signal = 'SELL';
            }

            // Only generate signal when position changes
            if ($signal && $signal !== $previousPosition) {
                $signals[] = [
                    'timestamp' => $timestampMs,
                    'time' => $formattedTime,
                    'type' => $signal,
                    'price' => $close[$i],
                    'message' => $signal === 'BUY'
                        ? 'EMA 50 crossed above EMA 200'
                        : 'EMA 50 crossed below EMA 200'
                ];
                $previousPosition = $signal;
            }
        }

        return response()->json([
            'status' => 'success',
            'signals' => $signals
        ]);
    }
}
