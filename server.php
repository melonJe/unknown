<?php

require_once __DIR__ . '/vendor/autoload.php';

set_error_handler(function ($level, $message, $file, $line) {});

set_exception_handler(function (Throwable $e) {});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
    }
});

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Service\Room;
use Service\User;
use Service\Dice;
use Service\Turn;

$ws_worker = new Worker("websocket://" . ($_ENV['WEBSOCKET_HOST'] ?? '0.0.0.0') . ":" . ($_ENV['WEBSOCKET_PORT'] ?? '8080'));

// 클라이언트 전체 목록
$ws_worker->connections = [];

// 연결 시
$ws_worker->onWebSocketConnect = function (TcpConnection $conn, Request $request) use (&$ws_worker) {
    try {
        $ws_worker->connections[$conn->id] = $conn;
        $conn->roomId = $request->get('roomId');
        $conn->userId = $request->get('userId');
    } catch (Throwable $e) {
    }
};

// 메시지 수신
$ws_worker->onMessage = function (TcpConnection $conn, $data) use (&$ws_worker) {
    try {
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
                $joined = Room::joinGame($msg['user_id'], $msg['room_id']);
                if (!$joined['success']) {
                    $conn->send(json_encode(['type' => 'error', 'action' => "goHome", 'message' => $joined['error']]));
                    break;
                }
                $board = Room::getBoard($msg['room_id']);
                $conn->send(json_encode(['type' => 'board_data', 'board' => $board]));
                foreach ($ws_worker->connections as $c) {
                    if ($c->roomId === $msg['room_id']) {
                        $c->send(json_encode(['type' => 'dices_data', 'dices' => User::getDices($msg['room_id'])]));
                    }
                }
                break;
            case 'start_game':
                $result = Room::startGame($msg['room_id']);
                if (!$result['success']) {
                    $conn->send(json_encode(['type' => 'error', 'message' => $result['error']]));
                    break;
                }
                foreach ($ws_worker->connections as $c) {
                    if ($c->roomId === $msg['room_id']) {
                        $c->send(json_encode(['type' => 'dices_data', 'dices' => User::getDices($msg['room_id'])]));
                        $c->send(json_encode(['type' => 'next_turn', 'user' => User::getUserData($msg['room_id'], $msg['user_id']), 'turn_order' => Turn::getTurnOrder($msg['room_id'])]));
                    }
                }
                break;
            case 'move':
                $result = Dice::move($msg['user_id'], $msg['room_id'], $msg['direction']);
                if (!$result['success']) {
                    $conn->send(json_encode(['type' => 'error', 'message' => $result['error']]));
                    break;
                }
                foreach ($ws_worker->connections as $c) {
                    if ($c->roomId === $msg['room_id']) {
                        $c->send(json_encode(['type' => 'dices_data', 'dices' => User::getDices($msg['room_id'])]));
                        $c->send(json_encode(['type' => 'next_turn', 'user' => User::getUserData($msg['room_id'], $msg['user_id']), 'turn_order' => Turn::getTurnOrder($msg['room_id'])]));
                        // if ($result['exile'] ?? false) {
                        //     $c->send(json_encode(['type' => 'exiled', 'user' => $msg['user_id']]));
                        // }
                        // if ($result['game_end'] ?? false) {
                        //     $c->send(json_encode(['type' => 'game_end']));
                        // }
                    }
                }
                break;
            case 'set_start':
                $ok = Room::setStartTile($msg['room_id'], $msg['user_id'], (int)$msg['x'], (int)$msg['y'], $msg['dice']);
                if (!$ok['success']) {
                    $conn->send(json_encode(['type' => 'error', 'message' => $ok['error'] ?? 'invalid start']));
                    break;
                }
                $turnService = new Turn();
                $newOrder = $turnService->advanceMoveTurn($msg['room_id'], [
                    'user'   => $msg['user_id'],
                    'action' => 'move',
                ]);
                foreach ($ws_worker->connections as $c) {
                    if ($c->roomId === $msg['room_id']) {
                        $c->send(json_encode(['type' => 'dices_data', 'dices' => User::getDices($msg['room_id'])]));
                        $c->send(json_encode(['type' => 'next_turn', 'user' => User::getUserData($msg['room_id'], $msg['user_id']), 'turn_order' => $newOrder]));
                    }
                }
                break;
            case 'set_dice_state':
                $result = Dice::setDiceState($msg['room_id'], $msg['user_id'], $msg['dice']);
                if (!$result['success']) {
                    $conn->send(json_encode(['type' => 'error', 'message' => $result['error']]));
                    break;
                }
                foreach ($ws_worker->connections as $c) {
                    if ($c->roomId === $msg['room_id']) {
                        $c->send(json_encode(['type' => 'dices_data', 'dices' => User::getDices($msg['room_id'])]));
                        $c->send(json_encode(['type' => 'next_turn', 'user' => User::getUserData($msg['room_id'], $msg['user_id']), 'turn_order' => Turn::getTurnOrder($msg['room_id'])]));
                    }
                }
                break;
            case 'target_move':
                $result = Dice::targetMove($msg['room_id'], $msg['user_id'], $msg['target_user_id'], $msg['direction']);
                if (!$result['success']) {
                    $conn->send(json_encode(['type' => 'error', 'message' => $result['error']]));
                    break;
                }
                foreach ($ws_worker->connections as $c) {
                    if ($c->roomId === $msg['room_id']) {
                        $c->send(json_encode(['type' => 'dices_data', 'dices' => User::getDices($msg['room_id'])]));
                        $c->send(json_encode(['type' => 'next_turn', 'user' => User::getUserData($msg['room_id'], $msg['user_id']), 'turn_order' => Turn::getTurnOrder($msg['room_id'])]));
                    }
                }
                break;
            case 'extra_turn':
                break;
            default:
                break;
        }
    } catch (Throwable $e) {
        $conn->send(json_encode(['type' => 'error', 'message' => 'server_error']));
    }
};

// 연결 종료 시
$ws_worker->onClose = function (TcpConnection $conn) use (&$ws_worker) {
    try {
        unset($ws_worker->connections[$conn->id]);
        $roomId = $conn->roomId;
        $userId = $conn->userId;
        User::deleteUserData($userId, $roomId);
        if (!empty($roomId)) {
            foreach ($ws_worker->connections as $c) {
                if ($c->roomId === $roomId) {
                    $c->send(json_encode([
                        'type'  => 'user_out',
                        'msg'   => "{$userId}님의 연결이 끊겼습니다.",
                        'dices' => User::getDices($roomId)
                    ]));
                }
            }
        }
    } catch (Throwable $e) {
    }
};

$ws_worker->onError = function ($connection, $code, $msg) {};

// 서버 실행
Worker::runAll();
