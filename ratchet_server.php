<?php
require __DIR__ . '/vendor/autoload.php';

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
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);
        $conn->roomId = $params['roomId'] ?? '';
        $conn->userId = $params['userId'] ?? '';
        $this->clients[$conn->resourceId] = $conn;
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = @json_decode($msg, true);
        if (!$data) {
            return;
        }
        switch ($data['action'] ?? '') {
            case 'subscribe':
                $from->send(json_encode(['type' => 'subscribed']));
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->clients[$conn->resourceId]);
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
