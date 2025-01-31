<?php

namespace App\Http\Controllers\Binance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    protected $binance;

    public function __construct()
    {
        $this->binance = new SetupController();
    }

    public function newOrder(string $side, array $params)
    {
        return $this->binance->sendBinanceRequest('fapi/v1/order', array_merge($params, ['side' => $side]), 'POST');
    }

    public function newBatchOrders(array $orders)
    {
        return $this->binance->sendBinanceRequest('fapi/v1/batchOrders', [
            'batchOrders' => json_encode($orders, JSON_UNESCAPED_SLASHES)
        ], 'POST');
    }



    public function cancelOrder(
        string  $symbol,
        ?int    $orderId = null,
        ?string $origClientOrderId = null,
        ?string $newClientOrderId = null,
        ?int    $recvWindow = null
    )
    {
        $params = array_filter([
            'symbol' => $symbol,
            'orderId' => $orderId,
            'origClientOrderId' => $origClientOrderId,
            'newClientOrderId' => $newClientOrderId,
            'recvWindow' => $recvWindow,
        ], fn($value) => $value !== null);

        return $this->binance->sendBinanceRequest('fapi/v1/order', $params, 'DELETE');
    }

    public function cancelAllOpenOrders(
        string $symbol,
        ?int $recvWindow = null
    )
    {
        $params = array_filter([
            'symbol' => $symbol,
            'recvWindow' => $recvWindow,
        ], fn($value) => $value !== null);

        return $this->binance->sendBinanceRequest('fapi/v1/allOpenOrders', $params, 'DELETE');
    }

    public function getAllOrders(
        string $symbol,
        ?int $orderId = null,
        ?int $startTime = null,
        ?int $endTime = null,
        ?int $limit = null,
        ?int $recvWindow = null
    )
    {
        $params = array_filter([
            'symbol' => $symbol,
            'orderId' => $orderId,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit,
            'recvWindow' => $recvWindow,
        ], fn($value) => $value !== null);

        return $this->binance->sendBinanceRequest('fapi/v1/allOrders', $params, 'GET');
    }

    public function getPositionInformation(
        string $symbol,
        ?int $recvWindow = null
    )
    {
        $params = array_filter([
            'symbol' => $symbol,
            'recvWindow' => $recvWindow,
        ], fn($value) => $value !== null);

        return $this->binance->sendBinanceRequest('fapi/v2/positionRisk', $params, 'GET');
    }

    public function addLeverage(
        string $symbol,
        int $leverage,
        ?int $recvWindow = null
    )
    {
        $params = array_filter([
            'symbol' => $symbol,
            'leverage' => $leverage,
            'recvWindow' => $recvWindow,
        ], fn($value) => $value !== null);

        return $this->binance->sendBinanceRequest('fapi/v1/leverage', $params, 'POST');
    }

    public function setPosition(
        string $dualSidePosition,
        ?int $recvWindow = null
    )
    {
        $params = array_filter([
            'dualSidePosition' => $dualSidePosition,
            'recvWindow' => $recvWindow,
        ], fn($value) => $value !== null);

        return $this->binance->sendBinanceRequest('fapi/v1/positionSide/dual', $params, 'POST');
    }
}
