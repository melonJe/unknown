<?php

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Service\Room;
use Service\User;
use Service\Dice;

$ws_worker = new Worker("websocket://0.0.0.0:8080");

// 클라이언트 전체 목록
$ws_worker->connections = [];

// 연결 시
$ws_worker->onWebSocketConnect  = function (TcpConnection $conn, Request $request) use (&$ws_worker) {
    // 전체 클라 목록에 추가
    $ws_worker->connections[$conn->id] = $conn;
    // 커스텀 프로퍼티로 저장
    $conn->roomId = $request->get('roomId');
    $conn->userId = $request->get('userId');
    // echo "New connection ({$conn->id})\n";
};

// 메시지 수신
$ws_worker->onMessage = function (TcpConnection $conn, $data) use (&$ws_worker) {
    $msg = @json_decode($data, true);

    if (!$msg) {
        $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid JSON']));
        return;
    }
    switch ($msg['action']) {
        case 'get_room_list':
            $rooms = Room::getRooms();
            $conn->send(json_encode(['type' => 'room_list', 'rooms' => $rooms]));
            break;
        case 'create_room':
            $roomId = Room::createRoom($msg['user_id']);
            foreach ($ws_worker->connections as $c) {
                $c->send(json_encode(['type' => 'room_list_changed']));
            }
            $conn->send(json_encode(['type' => 'room_created', 'room_id' => $roomId]));
            break;
        case 'join_room':
            Room::joinGame($msg['user_id'], $msg['room_id']);
            $conn->send(json_encode(['type' => 'board_data', 'board' => Room::getBoard($msg['room_id'])]));
            $conn->send(json_encode(['type' => 'dices_data', 'dices' => user::getDices($msg['room_id'])]));
            break;
        case 'move':
            Dice::move($msg['user_id'], $msg['room_id'], $msg['direction']);
            foreach ($ws_worker->connections as $c) {
                $c->send(json_encode(['type' => 'dices_data', 'dices' => user::getDices($msg['room_id'])]));
            }
            break;

        default:
            break;
    }
};

// 연결 종료 시
$ws_worker->onClose = function (TcpConnection $conn) use (&$ws_worker) {
    unset($ws_worker->connections[$conn->id]);
    $roomId = $conn->roomId;
    $userId = $conn->userId;
    // echo "{$userId}\n";
    User::deleteUserData($userId, $roomId);
    if (!empty($roomId)) {

        foreach ($ws_worker->connections as $c) {
            $c->send(json_encode(['type' => 'user_out', 'msg' => "{$userId}님의 연결이 끊겼습니다.", 'dices' => user::getDices($roomId)]));
        }
    }
};

// 서버 실행
Worker::runAll();
