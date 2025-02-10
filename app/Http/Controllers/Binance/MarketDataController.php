<?php

namespace App\Http\Controllers\Binance;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    protected $binance;
    public function __construct()
    {
        $this->binance = new SetupController();
    }

    public function getExchangeInformation()
    {
        return $this->binance->sendBinanceRequest('fapi/v1/exchangeInfo', [], 'GET');
    }

    function get_price_precision($symbol)
    {
        return $this->getSymbolData($symbol, 'pricePrecision');
    }

    function get_qty_precision($symbol)
    {
        return $this->getSymbolData($symbol, 'quantityPrecision');
    }

    private function getSymbolData($symbol, $key)
    {
        try {
            $exchangeInfo = $this->getExchangeInformation();

            foreach ($exchangeInfo['symbols'] as $elem) {
                if ($elem['symbol'] === $symbol) {
                    return $elem[$key] ?? null;
                }
            }

            return null;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    public function getCoinPrice($symbol)
    {
        try {
            $ticker = $this->binance->sendBinanceRequest('fapi/v1/premiumIndex', ['symbol' => $symbol], 'GET');
            return $ticker['markPrice'];
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    public function fetchBinanceData($symbole, $interval = '1h', $limit = 1500)
    {
        try {
            // Define your parameters (for example, symbol and interval)
            $params = [
                'symbol' => $symbole,
                'interval' => $interval,
                'limit' => $limit,
            ];

            $ticker = $this->binance->sendBinanceRequest("fapi/v1/klines", $params, "GET");

            return $ticker;
        } catch (\Exception $e) {

            return 'Error: ' . $e->getMessage();
        }
    }

    public function getFormatBinanceData($symbol, $interval, $limit = 1500)
    {
        $klines = $this->fetchBinanceData($symbol, $interval, $limit);
        $data = [];
        foreach ($klines as $k) {
            $data[] = [
                'open_time' => $k[0],
                'open'      => (float)$k[1],
                'high'      => (float)$k[2],
                'low'       => (float)$k[3],
                'close'     => (float)$k[4],
                'volume'    => (float)$k[5],
            ];
        }
        return $data;
    }

}
