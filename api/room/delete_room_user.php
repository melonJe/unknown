<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';
require_once LIB_PATH . '/redis.php'; // Redis 연결

try {
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }

    $redis = getRedis();

    // 모든 방(room) 목록 가져오기 (Set 사용 권장)
    $roomIds = $redis->smembers('rooms'); // SADD rooms {room_id} 형태로 관리할 때

    foreach ($roomIds as $roomId) {
        // 방의 유저 Set에서 해당 유저 제거
        $redis->srem("room:{$roomId}:users", $user_id);
        // 해당 방에서 유저별 정보 삭제 (hash)
        $redis->del("room:{$roomId}:user:{$user_id}");
    }

    // OK 응답
    echo json_encode(["result" => "left_all_rooms"]);
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "Redis error",
        "message" => $e->getMessage()
    ]);
}
