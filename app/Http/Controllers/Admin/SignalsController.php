<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Signals;
use App\Models\TradingPositions;
use Illuminate\Http\Request;

class SignalsController extends Controller
{
    public function getSignals(Request $request)
    {
        $symbol = $request->input('symbol');
        $sort_column = $request->input('sort_column', 'id');
        $sort_order = $request->input('sort_order', 'desc');
        $page = $request->input('page', 10);

        $signals = Signals::where('symbol', $symbol)
            ->orderBy($sort_column, $sort_order)
            ->take($page)
            ->get();

        return successResponse('', $signals);
    }

    public function getTrades(Request $request){
        $symbol = $request->input('symbol');
        $sort_column = $request->input('sort_column', 'id');
        $sort_order = $request->input('sort_order', 'desc');
        $page = $request->input('page', 10);

        $trades = TradingPositions::with('signal')
            ->orderBy($sort_column, $sort_order)
            ->take($page)
            ->get();

        return successResponse('', $trades);
    }
}
