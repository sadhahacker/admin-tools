<?php

namespace App\Http\Controllers\Binance;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    protected $apiKey;
    protected $secretKey;

    protected $http;
    protected $baseUrl = 'https://fapi.binance.com/';

    public function __construct()
    {
        $this->apiKey = "9kb6lfq1qGBnOtRl85X3OcyiIp17FdlaDapQT0erSiXvKaIHLspnuVJx9yE6mWlX";
        $this->secretKey = "M6xgF3VSOU4qv5l5anFokB8linV5fB2qahO0XxIiYkenaKUIKxi9mPEUBw5jxzS3";
        $this->http = new Client();
    }
    public function sendBinanceRequest(string $endpoint, array $params, string $method = 'POST')
    {
        // Add timestamp to params
        $params['timestamp'] = $this->getTimestamp();

        $params['recvWindow'] = $this->setRecvWindow('10000');

        // Generate signature and add to params
        $params['signature'] = $this->generateSignature($params);

        try {
            // Prepare request options
            $options = $this->prepareRequestOptions($params);

            $options['verify'] = false;

            // Send request
            $response = $this->http->request($method, $this->baseUrl . $endpoint, $options);

            // Return decoded response
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getTimestamp(): int
    {
        return round(microtime(true) * 1000);  // Return timestamp in milliseconds
    }

    private function generateSignature(array $params): string
    {
        // Build query string and generate signature
        $queryString = http_build_query($params);
        return hash_hmac('sha256', $queryString, $this->secretKey);
    }

    private function prepareRequestOptions(array $params): array
    {
        // Prepare headers and include params with signature
        return [
            'headers' => [
                'X-MBX-APIKEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'query' => $params,  // Include query parameters
        ];
    }
    public function setRecvWindow($seconds = "5000")
    {
        return $seconds;
    }
}
