<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php';

session_start();

$myUserId = $_SESSION['user_id'] ?? null;
$roomId = $_GET['room_id'] ?? null;

$roomKey = "room:{$roomId}";
$roomData = $redis->hgetall($roomId);
if (!$roomData['state']) {
    echo json_encode([
        'is_my_turn' => true
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if (!$myUserId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$roomId) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$redis = getRedis();
$turnUserId = $redis->hget("room:{$roomId}:turn", "current_turn_user_id");

if (!$turnUserId) {
    http_response_code(404);
    echo json_encode(['error' => 'Turn info not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

// boolean 결과를 JSON으로 반환
echo json_encode([
    'is_my_turn' => $myUserId === $turnUserId,
], JSON_UNESCAPED_UNICODE);
