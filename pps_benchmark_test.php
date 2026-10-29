<?php
set_time_limit(0);        // no duration-cap via php engine
ignore_user_abort(false); // abort if user-abort

$host = $_GET['endpoint'] ?? die("[-] Error! Endpoint must be specified. Aborting...");

if ($host === "127.0.0.1") die("[!] Loopback not accurate. Aborting...");

$time = intval($_GET['time'] ??  5); // default five seconds
$size = intval($_GET['size'] ?? 18); // default minimum frame size (IEEE 802.3)
$port = intval($_GET['port'] ?? mt_rand(49152, 65535)); // IANA

if ($time > 60) die("[!] Benchmark duration too abusive. Aborting...");
if ($time < 1) die("[-] Benchmark duration not long enough. Aborting...");

if ($size > 1440) die("[-] Cannot exceed MTU cap for accurate results. Aborting...");
if ($size < 18) die("[!] Payload too small. Eighteen bytes or more to avoid ethernet padding. Aborting...");

if ($port > 65535 || $port < 1) die("[-] Port invalid. Aborting...");

$payload = random_bytes($size);
$count   = 0; // track throughput revolutions

$socket = stream_socket_client("udp://{$host}:{$port}",$errno,$errstr,0,STREAM_CLIENT_CONNECT);
if (!$socket) die("[-] Kernel error. Unable to spawn socket:<br>{$errstr}");

$start = microtime(true);
$quit  = $start + $time;

while (microtime(true) < $quit) {
    stream_socket_sendto($socket, $payload);
    $count++;
}
@fclose($socket);

$duration = microtime(true) - $start;
$avg_pps = $count / $duration;
$total_data_bytes = $count * $size;
$total_data_mbits = ($total_data_bytes * 8) / 1000000;
$bandwidth_rate_mbps = $total_data_mbits / $duration;

print("Overview:<br>");
print("Revoluations : " . number_format($count) . "<br>");
print("Time Elapsed : " . number_format($duration, 4) . " seconds<br>");
print("Total PPS : " . number_format($avg_pps, 2) . " packets/sec<br>");
print("Bandwidth : " . number_format($bandwidth_rate_mbps, 2) . " mbps saturated<br>");
?>

