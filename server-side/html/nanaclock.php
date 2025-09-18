<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta');

// Write files in the same directory as this script
$latestPath = __DIR__ . '/latest_ip.txt';
$logPath    = __DIR__ . '/nanaclock.log';

// Accept from POST or GET
$ip = $_POST['ipaddress'] ?? $_GET['ipaddress'] ?? null;

// Optional: fall back to client IP if parameter not provided
if (!$ip) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip && str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip, 2)[0]);
    }
}

if (!$ip) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Missing 'ipaddress' parameter");
}

$ip = trim($ip);
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Invalid 'ipaddress' value");
}

$timestamp = date('Y-m-d H:i:s');
$logEntry = "{$timestamp} - {$ip}\n";

// Write latest IP
$latestOk = @file_put_contents($latestPath, $ip . PHP_EOL, LOCK_EX);
if ($latestOk === false) {
    http_response_code(500);
    $err = error_get_last()['message'] ?? 'unknown error';
    error_log("nanaclock: failed to write {$latestPath}: {$err}");
    exit("Server error: cannot write {$latestPath} (check file permissions/ownership)");
}

// Append to log file
$logOk = @file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
if ($logOk === false) {
    http_response_code(500);
    $err = error_get_last()['message'] ?? 'unknown error';
    error_log("nanaclock: failed to append {$logPath}: {$err}");
    exit("Server error: cannot write {$logPath} (check file permissions/ownership)");
}

header('Content-Type: text/plain; charset=utf-8');
echo "Nana received IP: {$ip} at {$timestamp}";
