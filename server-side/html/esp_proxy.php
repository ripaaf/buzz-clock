<?php
// esp_proxy.php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo "Only GET allowed";
    exit;
}

$esp_ip = isset($_GET['esp_ip']) ? $_GET['esp_ip'] : '';
$esp_path = isset($_GET['path']) ? $_GET['path'] : '';

if (!$esp_ip || !$esp_path) {
    http_response_code(400);
    echo "Missing esp_ip or path";
    exit;
}

// Sanitize
$esp_ip = preg_replace('/[^0-9\.]/', '', $esp_ip);
$esp_path = ltrim($esp_path, '/');

$url = "http://$esp_ip/$esp_path";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => $err ?: "HTTP $code"]);
    exit;
}

// set json header (if returned json)
header('Content-Type: application/json');
echo $response;