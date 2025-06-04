<?php

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';

header('Content-Type: application/json');

$roomId = $_GET['room_id'] ?? null;

if (!$roomId) {
    http_response_code(400);
    echo json_encode(["error" => "room_id is required"], JSON_UNESCAPED_UNICODE);
    exit;
}

$redis = getRedis();
$roomKey = "room:{$roomId}";
$boardJson = $redis->hget($roomKey, 'board');

if (!$boardJson) {
    http_response_code(404);
    echo json_encode(["error" => "room not found"], JSON_UNESCAPED_UNICODE);
    exit;
}

// board는 이미 JSON 문자열이므로 바로 출력
echo $boardJson;
