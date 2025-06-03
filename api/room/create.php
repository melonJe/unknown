<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';
require_once LIB_PATH . '/redis.php';

try {
    $redis = getRedis();

    // 방 ID 및 보드 데이터
    $roomId = substr(md5(uniqid()), 0, 10);
    $defaultBoard = json_decode(file_get_contents(DATA_PATH . '/board.json'), true);

    $roomKey = "room:{$roomId}";
    $createdAt = date('Y-m-d H:i:s');

    $redis->hmset($roomKey, [
        'room_id' => $roomId,
        'board' => json_encode($defaultBoard, JSON_UNESCAPED_UNICODE),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $redis->expire($roomKey, 60 * 60 * 24);

    echo json_encode(["room_id" => $roomId]);
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "Redis error",
        "message" => $e->getMessage()
    ]);
}
