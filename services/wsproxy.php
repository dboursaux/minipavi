<?php
$camilla = '127.0.0.1';
$camillaPort = 1234;

$client = fsockopen($camilla, $camillaPort, $errno, $errstr, 2);
if (!$client) { http_response_code(502); die("Cannot connect to CamillaDSP: $errstr"); }

// Get HTTP headers from client
$headers = getallheaders();
$key = $headers['Sec-Websocket-Key'] ?? '';
$host = $headers['Host'] ?? '';

// Send WebSocket upgrade request to CamillaDSP
$req = "GET / HTTP/1.1\r\n";
$req .= "Host: $camilla:$camillaPort\r\n";
$req .= "Upgrade: websocket\r\n";
$req .= "Connection: Upgrade\r\n";
$req .= "Sec-WebSocket-Key: $key\r\n";
$req .= "Sec-WebSocket-Version: 13\r\n";
$req .= "Origin: http://$host\r\n";
$req .= "\r\n";

fwrite($client, $req);
$resp = fread($client, 4096);

// Forward response to browser
header('HTTP/1.1 101 Switching Protocols');
header('Upgrade: websocket');
header('Connection: Upgrade');
foreach (explode("\r\n", trim($resp)) as $line) {
    if (stripos($line, 'HTTP/') === 0) continue;
    if (stripos($line, 'Sec-WebSocket-Accept:') === 0) {
        header($line);
        break;
    }
}

// Bidirectional relay
$done = false;
stream_set_blocking($client, false);
stream_set_blocking(fopen('php://stdin', 'r'), false);

while (!$done) {
    $r = [$client];
    $w = null; $e = null;
    if (stream_select($r, $w, $e, 2) > 0) {
        $data = fread($client, 65536);
        if ($data === false || $data === '') $done = true;
        else echo $data;
    }
}
