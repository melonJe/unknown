<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php';

$roomId = $_POST['room_id'] ?? null;
if (!$roomId) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$redis = getRedis();

// 턴 유저 목록(리스트)에 따라 순환
$userIds = $redis->lrange("room:{$roomId}:turn_order", 0, -1); // 순서 보장 리스트
if (!$userIds || count($userIds) === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'No turn order found'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 현재 턴 유저
$currentTurnUser = $redis->hget("room:{$roomId}:turn", "current_turn_user_id");

// 인덱스 탐색 및 순환
$currentIndex = array_search($currentTurnUser, $userIds, true);
if ($currentIndex === false) {
    $nextIndex = 0;
} else {
    $nextIndex = ($currentIndex + 1) % count($userIds);
}
$nextUserId = $userIds[$nextIndex];

// 턴 유저 갱신
$redis->hset("room:{$roomId}:turn", "current_turn_user_id", $nextUserId);

echo json_encode(['next' => $nextUserId], JSON_UNESCAPED_UNICODE);
