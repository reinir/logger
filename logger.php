<?php
/**
 * Logger
 * Version 1.1
 *
 * Copyright (c) 2022 Reinir Puradinata
 * All rights reserved
 */
 
file_put_contents('logger.log', $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' ' . json_encode(apache_request_headers()) . "\n", FILE_APPEND);

if (!isset($_SERVER['PATH_INFO'])) {
    file_put_contents('logger.log', "PATH REQUIRED\n", FILE_APPEND);
    http_response_code(400);
    header('content-type: application/json');
    echo json_encode([
        'status' => 400,
        'description' => 'Path required',
    ]);
    die();
}

$abc = explode('/', substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME'])), 4);

if (count($abc) < 4) {
    file_put_contents('logger.log', "PATH INCOMPLETE\n", FILE_APPEND);
    http_response_code(400);
    header('content-type: application/json');
    echo json_encode([
        'status' => 400,
        'description' => 'Path incomplete',
    ]);
    die();
}

$scheme = $abc[1];
$host = $abc[2];
$path = $abc[3];
$qstr = is_string($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$headers = apache_request_headers();
$h = [];
foreach ($headers as $i => $x) {
    $h[strtolower($i)] = $x;
}
$h['host'] = $host;
$h['connection'] = 'close';
$body = file_get_contents('php://input');
$context = stream_context_create();
stream_context_set_option($context, "ssl", "verify_peer", false);
stream_context_set_option($context, "ssl", "verify_peer_name", false);
$remote = $scheme ? "{$scheme}://{$host}" : $host;
$socket = stream_socket_client($remote, $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);

if (!$socket) {
    echo "<div>{$errno}</div>\n";
    echo "<div>{$errstr}</div>\n";
    echo "<div>{$path}</div>\n";
} else {
    $out = "{$_SERVER['REQUEST_METHOD']} /{$path} HTTP/1.0\r\n";
    foreach($h as $i => $x)
    $out.= "{$i}: {$x}\r\n";
    $out.= "\r\n" . $body;
    fwrite($socket, $out);
    
    $s = trim(fgets($socket, 1024));
    header($s);
    file_put_contents('logger.log', $s . "\n", FILE_APPEND);
    while (true) {
        $line = trim(fgets($socket, 1024));
        if ($line == '') {
            break;
        }
        file_put_contents('logger.log', $line . "\n", FILE_APPEND);
        $line = explode(': ', $line, 2);
        header("{$line[0]}: {$line[1]}");
    }
    file_put_contents('logger.log', "\n", FILE_APPEND);
    
    fpassthru($socket);
    fclose($socket);
}
