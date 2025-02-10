<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use InvalidArgumentException;

class TradingController extends Controller
{
    protected $account;
    protected $market;
    protected $trade;

    public function __construct()
    {
        $this->account = new Binance\AccountController();
        $this->market = new Binance\MarketDataController();
        $this->trade = new Binance\TradeController();
    }

    public function executeTrade(Request $request)
    {

        $request->validate([
            'action' => 'required|string',
            'coin' => 'required|string',
            'quantity' => 'required|numeric',
            'price' => 'required|numeric',
            'tp' => 'required|numeric',
            'sl' => 'required|numeric',
            'leverage' => 'required|numeric'
        ]);

        $clientAccount = $this->account->getAccountInformation();


        $usdtBalance = $this->getUsdtBalance();

        if ($usdtBalance < 0) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        if (!empty($clientAccount['positions'])) {
            return response()->json(['message' => 'Position already exists'], 400);
        }

        try {
            $response = match ($request->action) {
                'long' => $this->longPosition($request->coin, $request->quantity, $request->price, $request->tp, $request->sl, $request->leverage),
                'short' => $this->shortPosition($request->coin, $request->quantity, $request->price, $request->tp, $request->sl, $request->leverage),
            };


            foreach ($response as $values) {
                if(!isset($values['orderId'])){
                    return response()->json(['message' => 'Trade execution failed', 'error' => 'Trade Error']);
                }
            }

            return response()->json(['message' => 'Trade executed successfully', 'data' => $response], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Trade execution failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function executeTradePosition($action, $coin, $quantity, $price, $tp, $sl, $leverage)
    {
        // Set leverage
        $this->trade->addLeverage($coin, $leverage);

        // Set position mode
        $this->trade->setPosition(true);

        $qty_precision = $this->market->get_qty_precision($coin);
        $price_precision = $this->market->get_price_precision($coin);

        // Calculate the quantity
        $qty = round($quantity / $price, $qty_precision);

        // Place the initial order (BUY or SELL)
        $order = $this->trade->newOrder(
            strtoupper($action), // 'BUY' or 'SELL'
            [
                'symbol' => $coin,
                'type' => 'LIMIT',
                'quantity' => $qty,
                'timeInForce' => 'GTC',
                'price' => $price
            ]
        );

        // Place Stop Loss order
        $sl_price = round($sl, $price_precision);
        $stop_loss = $this->trade->newOrder(
            strtoupper($action == 'buy' ? 'sell' : 'buy'), // If action is 'buy', stop loss is 'sell', else 'buy'
            [
                'symbol' => $coin,
                'type' => 'STOP_MARKET',
                'quantity' => $qty,
                'timeInForce' => 'GTC',
                'stopPrice' => $sl_price
            ]
        );

        // Place Take Profit order
        $tp_price = round($tp, $price_precision);
        $take_profit = $this->trade->newOrder(
            strtoupper($action == 'buy' ? 'sell' : 'buy'), // If action is 'buy', take profit is 'sell', else 'buy'
            [
                'symbol' => $coin,
                'type' => 'TAKE_PROFIT_MARKET',
                'quantity' => $qty,
                'timeInForce' => 'GTC',
                'stopPrice' => $tp_price
            ]
        );

        return [
            strtolower($action) => $order['orderId'],
            'stop_loss' => $stop_loss['orderId'],
            'take_profit' => $take_profit['orderId']
        ];

    }


    public function executeBatchTradePosition($action, $coin, $quantity, $price, $tp, $sl, $leverage)
    {
        // Set leverage
        $this->trade->addLeverage($coin, $leverage);

        // Set position mode
        $this->trade->setPosition(true);

        $qty_precision = $this->market->get_qty_precision($coin);
        $price_precision = $this->market->get_price_precision($coin);

        // Calculate the quantity
        $qty = round($quantity / $price, $qty_precision);

        $order = [
            [
                'side' => strtoupper($action),
                'symbol' => (string) $coin,
                'type' => 'LIMIT',
                'quantity' => (string) $qty,
                'timeInForce' => 'GTC',
                'price' => (string) $price
            ],
            [
                'side' => strtoupper($action == 'buy' ? 'sell' : 'buy'),
                'symbol' => (string) $coin,
                'type' => 'STOP_MARKET',
                'timeInForce' => 'GTC',
                'stopPrice' => (string) round($sl, $price_precision),
                'closePosition' => "true"
            ],
            [
                'side' => strtoupper($action == 'buy' ? 'sell' : 'buy'),
                'symbol' => (string) $coin,
                'type' => 'TAKE_PROFIT_MARKET',
                'timeInForce' => 'GTC',
                'stopPrice' => (string) round($tp, $price_precision),
                'closePosition' => "true"
            ]
        ];

        $response = $this->trade->newBatchOrders($order);

        return [
            strtolower($action) => $response,
        ];

    }

    public function longPosition($coin, $quantity, $price, $tp, $sl, $leverage)
    {
        return $this->executeBatchTradePosition('buy', $coin, $quantity, $price, $tp, $sl, $leverage);
    }

    public function shortPosition($coin, $quantity, $price, $tp, $sl, $leverage)
    {
        return $this->executeBatchTradePosition('sell', $coin, $quantity, $price, $tp, $sl, $leverage);
    }
    public function getUsdtBalance()
    {
        $clientAccount = $this->account->getAccountInformation();
        foreach ($clientAccount['assets'] as $balance) {
            if ($balance['asset'] == 'USDT') {
                return $balance['availableBalance'];
            }
        }
        return 0;
    }
}
