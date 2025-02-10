<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Binance\MarketDataController;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SampleTrading extends Controller
{
    public function market(array $request)
    {
        $symbol = $request['symbol'] ?? null;
        $interval = $request['interval'] ?? '1h';  // Default value for safety
        $limit = $request['limit'] ?? 500;
        $tpPercentage = $request['tp'] ?? 3;
        $slPercentage = $request['sl'] ?? 3;

        $data = (new MarketDataController())->getFormatBinanceData($symbol, $interval, $limit);
        $newSignals = self::generateSignals($data, 9, 21, 14, 70, 30, $tpPercentage, $slPercentage);

        return $newSignals; // Return array instead of JSON response
    }

    public static function calculateEma(array $data, int $period): array
    {
        $closes = array_column($data, 'close');
        $ema = trader_ema($closes, $period);
        return $ema ? array_values($ema) : [];
    }

    public static function calculateRsi(array $data, int $period = 14): array
    {
        $closes = array_column($data, 'close');
        $rsi = trader_rsi($closes, $period);
        return $rsi ? array_values($rsi) : [];
    }

    public static function generateSignals(array $data, int $shortEma = 9, int $longEma = 21, int $rsiPeriod = 14, int $rsiOverbought = 70, int $rsiOversold = 30, float $tpPercentage = 2, float $slPercentage = 2): array
    {
        $emaShort = self::calculateEma($data, $shortEma);
        $emaLong = self::calculateEma($data, $longEma);
        $rsi = self::calculateRsi($data, $rsiPeriod);

        $signals = [];
        $activeTrade = null;

        foreach ($data as $index => $item) {
            if (!isset($emaShort[$index], $emaLong[$index], $rsi[$index])) {
                continue;
            }

            // ✅ **Check if TP or SL is hit**
            if ($activeTrade) {
                if (($activeTrade['signal'] === 'BUY' && $item['close'] >= $activeTrade['tp'])) {
                    // ✅ **Take Profit Hit**
                    $signals[] = [
                        'execution_time' => $item['open_time'],
                        'entry_price' => $activeTrade['entry_price'],
                        'close_price' => $item['close'],
                        'signal' => 'BUY',
                        'status' => 'TP HIT'
                    ];
                    $activeTrade = null;
                } elseif (($activeTrade['signal'] === 'BUY' && $item['close'] <= $activeTrade['sl'])) {
                    // ✅ **Stop Loss Hit**
                    $signals[] = [
                        'execution_time' =>$item['open_time'],
                        'entry_price' => $activeTrade['entry_price'],
                        'close_price' => $item['close'],
                        'signal' => 'BUY',
                        'status' => 'SL HIT'
                    ];
                    $activeTrade = null;
                } elseif (($activeTrade['signal'] === 'SELL' && $item['close'] <= $activeTrade['tp'])) {
                    // ✅ **Take Profit Hit for SELL**
                    $signals[] = [
                        'execution_time' => $item['open_time'],
                        'entry_price' => $activeTrade['entry_price'],
                        'close_price' => $item['close'],
                        'signal' => 'SELL',
                        'status' => 'TP HIT'
                    ];
                    $activeTrade = null;
                } elseif (($activeTrade['signal'] === 'SELL' && $item['close'] >= $activeTrade['sl'])) {
                    // ✅ **Stop Loss Hit for SELL**
                    $signals[] = [
                        'execution_time' => $item['open_time'],
                        'entry_price' => $activeTrade['entry_price'],
                        'close_price' => $item['close'],
                        'signal' => 'SELL',
                        'status' => 'SL HIT'
                    ];
                    $activeTrade = null;
                }
            }

            // ✅ **Check for new BUY/SELL trade**
            if (!$activeTrade) {
                $signal = '';
                if ($emaShort[$index] > $emaLong[$index] && ($index == 0 || $emaShort[$index - 1] <= $emaLong[$index - 1]) && $rsi[$index] < $rsiOverbought) {
                    $signal = 'BUY';
                } elseif ($emaShort[$index] < $emaLong[$index] && ($index == 0 || $emaShort[$index - 1] >= $emaLong[$index - 1]) && $rsi[$index] > $rsiOversold) {
                    $signal = 'SELL';
                }

                if ($signal) {
                    $entryPrice = $item['close'];

                    // ✅ **Calculate TP & SL using percentage**
                    $tp = ($signal === 'BUY') ? $entryPrice * (1 + ($tpPercentage / 100)) : $entryPrice * (1 - ($tpPercentage / 100));
                    $sl = ($signal === 'BUY') ? $entryPrice * (1 - ($slPercentage / 100)) : $entryPrice * (1 + ($slPercentage / 100));

                    $activeTrade = [
                        'open_time' => $item['open_time'],
                        'entry_price' => $entryPrice,
                        'ema_short' => $emaShort[$index],
                        'ema_long' => $emaLong[$index],
                        'rsi' => $rsi[$index],
                        'signal' => $signal,
                        'tp' => $tp,
                        'sl' => $sl,
                        'status' => 'PENDING'
                    ];

                    // ✅ **Log separate BUY/SELL signal**
                    $signals[] = [
                        'open_time' => $activeTrade['open_time'],
                        'entry_price' => $activeTrade['entry_price'],
                        'signal' => $signal,
                        'tp' => $activeTrade['tp'],
                        'sl' => $activeTrade['sl'],
                        'status' => 'NEW SIGNAL'
                    ];
                }
            }
        }

        return $signals;
    }
}
