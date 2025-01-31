<?php

namespace App\Http\Controllers\Binance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    protected $binance;
    public function __construct()
    {
        $this->binance = new SetupController();
    }

    public function getAccountInformation()
    {
        return $this->binance->sendBinanceRequest('fapi/v3/account', [], 'GET');
    }
    public function getAccountBalance()
    {
        return $this->binance->sendBinanceRequest('fapi/v3/balance', [], "GET");
    }

    public function futureAccountConfiguration()
    {
        return $this->binance->sendBinanceRequest('fapi/v1/account', [], 'GET');
    }
}
