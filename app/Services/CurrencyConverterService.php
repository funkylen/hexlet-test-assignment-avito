<?php

namespace App\Services;

use App\Exceptions\CurrencyConverterServiceException;
use Http;
use Log;

class CurrencyConverterService
{
    private const HOST = 'https://api.apilayer.com';
    private const APIKEY = 'm8W7JXpZe7AuUPzbaLflTpx1P79WpEEn';

    /**
     * array:5 [
     * "success" => true
     *   "query" => array:3 [
     *       "from" => "USD"
     *       "to" => "RUB"
     *       "amount" => 100
     *   ]
     *   "info" => array:2 [
     *       "timestamp" => 1659870363
     *       "rate" => 60.525038
     *   ]
     *   "date" => "2022-08-07"
     *   "result" => 6052.5038
     * ]
     */
    public function convert(string $from, string $to, float $amount)
    {
        $method = '/exchangerates_data/convert';
        $qs = "?to={$to}&from={$from}&amount={$amount}";

        $url = self::HOST . $method . $qs;

        $response = Http::withHeaders(['apikey' => self::APIKEY])->get($url);

        if (!$response->ok()) {
            Log::error($response->json());
            throw new CurrencyConverterServiceException(__('Something went wrong with currency transact.'));
        }

        $data = $response->json();

        return [
            'from_currency' => $data['query']['from'],
            'from_amount' => $amount,

            'to_currency' => $data['query']['to'],
            'to_amount' => $data['result'],

            'rate' => $data['info']['rate'],
        ];
    }
}
