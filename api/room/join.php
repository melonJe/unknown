<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php'; // getRedis 함수 포함

session_start();

try {
    $roomId = $_POST['room_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$roomId || !$userId) {
        http_response_code(400);
        echo json_encode(["error" => "room_id and user_id are required"]);
        exit;
    }

    $redis = getRedis();

    // 방 정보 로드
    $roomKey = "room:{$roomId}";
    $roomData = $redis->hgetall($roomKey);

    if (empty($roomData)) {
        http_response_code(404);
        echo json_encode(["error" => "room not found"]);
        exit;
    }

    // board 정보 파싱
    $board = json_decode($roomData['board'] ?? '', true);
    $tiles = $board['tiles'] ?? [];

    // 유저 상태 로드
    $userKey = "room:{$roomId}:user:{$userId}";
    $userData = $redis->hgetall($userKey);

    // 유저가 이미 입장했다면 바로 정보 반환
    if (!empty($userData['pos_x']) && !empty($userData['pos_y'])) {
        $player = [
            "user_id" => $userId,
            "pos_x" => $userData['pos_x'],
            "pos_y" => $userData['pos_y'],
            "dice" => json_decode($userData['dice'] ?? '{}', true),
        ];
    } else {
        // start 타일 중 무작위 선택
        $startTiles = array_values(array_filter($tiles, fn($t) => ($t['type'] ?? '') === 'start'));
        if (empty($startTiles)) {
            http_response_code(500);
            echo json_encode(["error" => "No start tiles found"]);
            exit;
        }
        $startTile = $startTiles[random_int(0, count($startTiles) - 1)];

        // 기본 주사위 정보
        $diceArr = [
            'top' => 'red',
            'bottom' => 'blue',
            'left' => 'green',
            'right' => 'yellow',
            'front' => 'white',
            'back' => 'purple',
        ];

        // 유저 상태 저장 및 갱신
        $redis->hmset($userKey, [
            'pos_x' => $startTile['x'],
            'pos_y' => $startTile['y'],
            'dice' => json_encode($diceArr, JSON_UNESCAPED_UNICODE),
            'joined_at' => date('Y-m-d H:i:s')
        ]);
        $redis->expire($userKey, 1800); // 1시간

        $player = [
            "user_id" => $userId,
            "pos_x" => $startTile['x'],
            "pos_y" => $startTile['y'],
            "dice" => $diceArr,
        ];
    }

    // 유저 목록에 추가, 만료 갱신
    $usersKey = "room:{$roomId}:users";
    $redis->sadd($usersKey, $userId);
    $redis->expire($usersKey, 1800); // 1시간

    $roomsKey = "rooms";
    $redis->sadd($roomsKey, $roomId);
    $redis->expire($roomsKey, 1800); // 1시간

    // 세션 갱신
    $_SESSION['room_id'] = $roomId;

    echo json_encode([
        "status" => "joined",
        "room" => [
            "room_id" => $roomId,
            "board" => $board
        ],
        "player" => $player
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "Redis error",
        "message" => $e->getMessage()
    ]);
}
