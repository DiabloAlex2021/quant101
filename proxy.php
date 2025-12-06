<?php
// proxy.php
// Simple Yahoo Finance proxy with CORS headers

// CORS / access headers so the browser can call this from your HTML
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Read ticker from query: ?t=AAPL
$ticker = isset($_GET['t']) ? trim($_GET['t']) : '';

if ($ticker === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ticker parameter "t"']);
    exit;
}

$url = 'https://query1.finance.yahoo.com/v7/finance/quote?symbols=' . urlencode($ticker);

// Fetch helper
function yahoo_fetch($url)
{
    // Prefer cURL if available
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 StockChatBot/1.0',
        ]);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            return [null, "Yahoo request failed (HTTP $code) $err"];
        }

        return [$body, null];
    }

    // Fallback: file_get_contents
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Mozilla/5.0 StockChatBot/1.0\r\n",
            'timeout' => 10,
        ]
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return [null, 'file_get_contents failed to reach Yahoo Finance'];
    }

    return [$body, null];
}

list($body, $error) = yahoo_fetch($url);

if ($error !== null) {
    http_response_code(502);
    echo json_encode(['error' => $error]);
    exit;
}

// Make sure Yahoo response is JSON
json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'Yahoo response was not valid JSON']);
    exit;
}

// Pass Yahoo JSON straight through
echo $body;
