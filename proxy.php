<?php
// proxy.php - Yahoo Finance proxy: ?t=AAPL

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$ticker = isset($_GET['t']) ? trim($_GET['t']) : '';

if ($ticker === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ticker parameter t']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9\.\-^=]+$/', $ticker)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ticker']);
    exit;
}

$yahooUrl = 'https://query1.finance.yahoo.com/v7/finance/quote?symbols=' . urlencode($ticker);

// Try cURL first, fallback to file_get_contents
$response = '';
$httpCode = 0;
$errorMsg = '';

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $yahooUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $response = curl_exec($ch);
    $errorMsg = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Mozilla/5.0\r\n",
            'timeout' => 10
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($yahooUrl, false, $context);
    if ($response === false) {
        $errorMsg = 'file_get_contents error';
    }
    $httpCode = 200; // best-effort, PHP doesn’t expose easily without parsing headers
}

if ($response === false || $response === '' || $errorMsg !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream error: ' . $errorMsg]);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid JSON from Yahoo']);
    exit;
}

echo json_encode($data);
