<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';
require_once LIB_PATH . '/redis.php'; // getRedis 함수 포함

session_start();

try {
    $roomId = $_POST['room_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$roomId || !$user_id) {
        http_response_code(400);
        echo json_encode(["error" => "room_id and user_id are required"]);
        exit;
    }

    $redis = getRedis();

    // 방(board) 정보 로드
    $roomKey = "room:{$roomId}";
    $roomData = $redis->hgetall($roomKey);

    if (!$roomData) {
        http_response_code(404);
        echo json_encode(["error" => "room not found"]);
        exit;
    }

    // board(tiles) 정보 파싱
    $board = json_decode($roomData['board'], true);
    $boardTiles = $board['tiles'] ?? [];

    // 출발 구역 찾기: type == "start" 인 타일 모음
    $startTiles = array_values(array_filter($boardTiles, function ($tile) {
        return isset($tile['type']) && $tile['type'] === 'start';
    }));

    if (count($startTiles) === 0) {
        http_response_code(500);
        echo json_encode(["error" => "No start tiles found"]);
        exit;
    }
    $startTile = $startTiles[random_int(0, count($startTiles) - 1)];
    file_put_contents('php://stdout', json_encode($startTile));

    // 유저 상태 Hash 저장
    $userKey = "room:{$roomId}:user:{$user_id}";
    $diceArr = [
        'top' => 'red',
        'bottom' => 'blue',
        'left' => 'green',
        'right' => 'yellow',
        'front' => 'white',
        'back' => 'purple',
    ];
    $redis->hmset($userKey, [
        'pos_x' => $startTile['x'],
        'pos_y' => $startTile['y'],
        'dice' => json_encode($diceArr, JSON_UNESCAPED_UNICODE),
        'joined_at' => date('Y-m-d H:i:s')
    ]);

    // 방의 유저 Set에 등록
    $redis->sadd("room:{$roomId}:users", $user_id);

    // (선택) 유저별 방 Set 관리
    $redis->sadd("user:{$user_id}:rooms", $roomId);

    // 세션 갱신
    $_SESSION['room_id'] = $roomId;

    echo json_encode([
        "status" => "joined",
        "room" => [
            "room_id" => $roomId,
            "board" => $board
        ],
        "player" => [
            "user_id" => $user_id,
            "pos_x" => $startTile['x'],
            "pos_y" => $startTile['y'],
            "dice" => $diceArr
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "Redis error",
        "message" => $e->getMessage()
    ]);
}
