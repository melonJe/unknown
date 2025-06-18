<?php
require __DIR__ . '/vendor/autoload.php';

set_error_handler(function ($level, $message, $file, $line) {
    echo "[Error] {$message} in {$file}:{$line}\n";
});

set_exception_handler(function (Throwable $e) {
    echo "[Exception] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n";
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        echo "[Fatal] {$error['message']} in {$error['file']}:{$error['line']}\n";
    }
});

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Predis\Client;

class GameSocket implements MessageComponentInterface
{
    private Client $redis;
    private array $clients = [];

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        try {
            $query = $conn->httpRequest->getUri()->getQuery();
            parse_str($query, $params);
            $conn->roomId = $params['roomId'] ?? '';
            $conn->userId = $params['userId'] ?? '';
            $this->clients[$conn->resourceId] = $conn;
        } catch (\Throwable $e) {
            echo "[Open Exception] {$e->getMessage()}\n";
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = @json_decode($msg, true);
            if (!$data) {
                return;
            }
            switch ($data['action'] ?? '') {
                case 'subscribe':
                    $from->send(json_encode(['type' => 'subscribed']));
                    break;
            }
        } catch (\Throwable $e) {
            echo "[Message Exception] {$e->getMessage()}\n";
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        try {
            unset($this->clients[$conn->resourceId]);
        } catch (\Throwable $e) {
            echo "[Close Exception] {$e->getMessage()}\n";
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }
}

$redis = new Client([
    'host' => '127.0.0.1',
    'port' => 6379
]);

$server = Ratchet\Server\IoServer::factory(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(
            new GameSocket($redis)
        )
    ),
    8080
);

$server->run();
