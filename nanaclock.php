<?php
date_default_timezone_set('Asia/Jakarta');

// Accept from POST or GET
$ip = $_POST['ipaddress'] ?? $_GET['ipaddress'] ?? null;

if ($ip) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "$timestamp - $ip\n";
    
    // Save latest IP
    file_put_contents('latest_ip.txt', $ip);

    // Append to log file
    file_put_contents('nanaclock.log', $log_entry, FILE_APPEND);

    echo "Nana received IP: $ip at $timestamp";
} else {
    echo "Missing 'ipaddress' parameter, master~";
}