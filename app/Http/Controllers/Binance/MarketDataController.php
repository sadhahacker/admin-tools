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

    function getCoinPrice($symbol)
    {
        try {
            $ticker = $this->binance->sendBinanceRequest('fapi/v1/premiumIndex', ['symbol' => $symbol], 'GET');
            return $ticker['markPrice'];
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
