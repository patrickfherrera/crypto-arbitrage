<?php

return [
    'api' => env('BINANCE_API', 'https://api.binance.com'),
    'api_key' => env('BINANCE_API_KEY'),
    'api_secret' => env('BINANCE_API_SECRET'),
    'order_url' => env('BINANCE_API_ORDER_URL', '/api/v3/order?'),
    'send_quote_convert_url' => env('BINANCE_API_SEND_QUOTE_CONVERT_URL', '/sapi/v1/convert/getQuote?'),
    'accept_quote_convert_url' => env('BINANCE_API_ACCEPT_QUOTE_CONVERT_URL', '/sapi/v1/convert/acceptQuote?'),
    'taker_fee' => (float) env('BINANCE_TAKER_FEE', 0.001),
    // Only log rows with profit_pct above this (cuts deep losers).
    'log_min_profit_pct' => (float) env('BINANCE_LOG_MIN_PROFIT_PCT', -0.02),
    // Live only if profit_pct >= this (after fees). 0.05 = 0.05%.
    'min_execute_profit_pct' => (float) env('BINANCE_MIN_EXECUTE_PROFIT_PCT', 0.05),
    // Live only if clamped capital is at least this (USDT).
    'min_execute_capital' => (float) env('BINANCE_MIN_EXECUTE_CAPITAL', 10),
    // Skip green execute / treat as stale when max book age exceeds this (ms).
    'max_quote_age_ms' => (int) env('BINANCE_MAX_QUOTE_AGE_MS', 300),
    // best | prefer_reverse | reverse_only | forward_only
    'direction_mode' => env('BINANCE_DIRECTION_MODE', 'prefer_reverse'),
    // Cap size to this fraction of top-of-book qty (0.25 = 25%).
    'depth_fill_fraction' => (float) env('BINANCE_DEPTH_FILL_FRACTION', 0.25),
    'use_account_fee' => filter_var(env('BINANCE_USE_ACCOUNT_FEE', true), FILTER_VALIDATE_BOOL),
    'confirm_green' => filter_var(env('BINANCE_CONFIRM_GREEN', true), FILTER_VALIDATE_BOOL),
];
