<?php

return [
    'api' => env('BINANCE_API', 'https://api.binance.com'),
    'api_key' => env('BINANCE_API_KEY'),
    'api_secret' => env('BINANCE_API_SECRET'),
    'order_url' => env('BINANCE_API_ORDER_URL', '/api/v3/order?'),
    'send_quote_convert_url' => env('BINANCE_API_SEND_QUOTE_CONVERT_URL', '/sapi/v1/convert/getQuote?'),
    'accept_quote_convert_url' => env('BINANCE_API_ACCEPT_QUOTE_CONVERT_URL', '/sapi/v1/convert/acceptQuote?'),
];