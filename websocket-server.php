<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Services\WebSocketServer;
use App\Utils\Config;

// Load configuration
Config::load();

$host = Config::get('WEBSOCKET_HOST', '0.0.0.0');
$port = Config::get('WEBSOCKET_PORT', 8080);

echo "Starting WebSocket server on {$host}:{$port}\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketServer()
        )
    ),
    $port,
    $host
);

echo "WebSocket server is running...\n";
echo "Press Ctrl+C to stop the server\n";

$server->run();

