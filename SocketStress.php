<?php

// 1,200 concurrent sockets consumed

declare(strict_types=1);

// Configuration
$targetIp   = '127.0.0.1';
$targetPort = 9000;

$connectionLifetime = 60.0;  // seconds
$creationInterval   = 0.050; // 50 milliseconds
$connectTimeout     = 5.0;   // seconds

// Suppress all PHP errors/warnings/notices
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '0');


// Connection tracking
$connections = [];

$nextCreation = microtime(true);


// Main loop
while (true) {

    $now = microtime(true);


    // Create a new TCP connection every 50 ms
    if ($now >= $nextCreation) {

        $errno  = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            "tcp://{$targetIp}:{$targetPort}",
            $errno,
            $errstr,
            $connectTimeout,
            STREAM_CLIENT_CONNECT
        );

        if ($socket !== false) {

            stream_set_blocking($socket, false);

            $connections[] = [
                'socket'    => $socket,
                'expiresAt' => $now + $connectionLifetime,
            ];
        }

        // Keep the generation schedule anchored to the
        // original interval rather than accumulating drift.
        $nextCreation += $creationInterval;

        // Resynchronize if the process falls significantly behind.
        if ($nextCreation < $now - 1.0) {
            $nextCreation = $now + $creationInterval;
        }
    }


    // Close connections that have exceeded 60 seconds
    foreach ($connections as $key => $connection) {

        if ($now >= $connection['expiresAt']) {

            @fclose($connection['socket']);

            unset($connections[$key]);
        }
    }
    // Re-index the array once per loop iteration, but only if items were actually removed
    $connections = array_values($connections);

    // Small sleep to prevent the loop from consuming an
    // entire CPU core while waiting for the next connection.
    usleep(1000);
}

