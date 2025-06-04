<?php
if (!defined('_GNUBOARD_')) exit; // Direct access prohibited

// Placeholder for GnuBoard cache functions.
// We will replace this with actual GnuBoard cache functions later.
function g5_set_cache($name, $data, $ttl) {
    $cache_file = G5_DATA_PATH.'/cache/'.$name.'.php';
    $content = '<?php if (!defined('_GNUBOARD_')) exit; ?>'.PHP_EOL;
    $content .= '/*'.PHP_EOL;
    $content .= 'name: '.$name.PHP_EOL;
    $content .= 'ttl: '.$ttl.PHP_EOL;
    $content .= 'time: '.time().PHP_EOL;
    $content .= '*/'.PHP_EOL;
    $content .= serialize($data);
    file_put_contents($cache_file, $content);
}

function g5_get_cache($name) {
    $cache_file = G5_DATA_PATH.'/cache/'.$name.'.php';
    if (file_exists($cache_file)) {
        $content = file_get_contents($cache_file);
        preg_match('|\/\*.*ttl: (\d+).*time: (\d+).*?\*\/|s', $content, $matches);
        if (isset($matches[1]) && isset($matches[2])) {
            if (time() > $matches[2] + $matches[1]) { // Cache expired
                @unlink($cache_file);
                return false;
            }
            $content = preg_replace('|\<\?php if \(!defined\('_GNUBOARD_'\)\) exit; \?\>|s', '', $content);
            $content = preg_replace('|\/\*.*?\*\/|s', '', $content);
            return unserialize(trim($content));
        }
    }
    return false;
}

/**
 * Fetches data from a given URL.
 *
 * @param string $url The URL to fetch.
 * @return array|false The decoded JSON data as an array, or false on error.
 */
function fetch_remote_data($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider security implications for production
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds timeout for connection
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);      // 20 seconds timeout for the entire transfer
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $output) {
        return json_decode($output, true);
    }
    return false;
}

/**
 * Retrieves Kimchi Premium data, utilizing cache.
 *
 * @return array An array containing premium, timestamp, and raw data rows.
 *               Returns an array with an error message if fetching fails.
 */
function get_kimchi_premium_data() {
    $cache_key = 'kimchi_premium_data';
    // Get cache duration from config, default to 60 seconds
    // This will be properly integrated when kimchi_premium.config.php is built
    $cache_ttl = defined('KIMCHI_PREMIUM_CACHE_TTL') ? KIMCHI_PREMIUM_CACHE_TTL : 60;

    $cached_data = g5_get_cache($cache_key);
    if ($cached_data) {
        return $cached_data;
    }

    // API Endpoints -  These are illustrative and might need to be updated
    $upbit_url = 'https://api.upbit.com/v1/ticker?markets=KRW-BTC'; // Placeholder
    $binance_url = 'https://api.binance.com/api/v3/ticker/price?symbol=BTCUSDT'; // Placeholder
    $exchange_rate_url = 'https://quotation-api-cdn.dunamu.com/v1/forex/recent?codes=FRX.KRWUSD'; // Placeholder for USD to KRW

    $upbit_data = fetch_remote_data($upbit_url);
    $binance_data = fetch_remote_data($binance_url);
    $exchange_rate_data = fetch_remote_data($exchange_rate_url);

    $error_messages = [];

    if (!$upbit_data || !isset($upbit_data[0]['trade_price'])) {
        $error_messages[] = 'Failed to fetch or parse Upbit data.';
    }
    if (!$binance_data || !isset($binance_data['price'])) {
        $error_messages[] = 'Failed to fetch or parse Binance data.';
    }
    if (!$exchange_rate_data || !isset($exchange_rate_data[0]['basePrice'])) {
        $error_messages[] = 'Failed to fetch or parse exchange rate data.';
    }

    if (!empty($error_messages)) {
        return ['error' => implode(' ', $error_messages), 'premium' => 0, 'timestamp' => time(), 'rows' => []];
    }

    $upbit_price_krw = (float)$upbit_data[0]['trade_price'];
    $binance_price_usdt = (float)$binance_data['price'];
    $usd_krw_rate = (float)$exchange_rate_data[0]['basePrice'];

    if ($usd_krw_rate == 0) { // Avoid division by zero
        return ['error' => 'Exchange rate is zero.', 'premium' => 0, 'timestamp' => time(), 'rows' => []];
    }

    $binance_price_krw = $binance_price_usdt * $usd_krw_rate;
    $premium = 0;
    if ($binance_price_krw > 0) { // Avoid division by zero if binance price is zero
        $premium = (($upbit_price_krw - $binance_price_krw) / $binance_price_krw) * 100;
    }


    $result = [
        'upbit_krw_btc' => $upbit_price_krw,
        'binance_btc_usdt' => $binance_price_usdt,
        'usd_krw_rate' => $usd_krw_rate,
        'binance_krw_btc' => $binance_price_krw,
        'premium' => round($premium, 2), // Round to 2 decimal places
        'timestamp' => time(),
        'rows' => [
            ['source' => 'Upbit', 'price_krw' => $upbit_price_krw, 'currency' => 'KRW'],
            ['source' => 'Binance', 'price_usdt' => $binance_price_usdt, 'price_krw' => $binance_price_krw, 'currency' => 'USDT/KRW'],
            ['source' => 'Exchange Rate', 'rate' => $usd_krw_rate, 'currency' => 'USD/KRW']
        ]
    ];

    g5_set_cache($cache_key, $result, $cache_ttl);

    return $result;
}
?>
