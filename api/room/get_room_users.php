<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';
require_once LIB_PATH . '/redis.php'; // getRedis 함수 포함

$roomId = $_GET['room_id'] ?? null;

if (!$roomId) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id is required']);
    exit;
}

try {
    $redis = getRedis();
    $userIds = $redis->smembers("room:{$roomId}:users");

    $users = [];
    foreach ($userIds as $user_id) {
        $userKey = "room:{$roomId}:user:{$user_id}";
        $data = $redis->hgetall($userKey);

        // 필드 일부만 추출, dice는 JSON 문자열이므로 파싱
        if ($data) {
            $users[] = [
                'user_id' => $user_id,
                'pos_x' => isset($data['pos_x']) ? (int) $data['pos_x'] : 0,
                'pos_y' => isset($data['pos_y']) ? (int) $data['pos_y'] : 0,
                'dice' => isset($data['dice']) ? json_decode($data['dice'], true) : [],
            ];
        }
    }

    echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "Redis error",
        "message" => $e->getMessage()
    ]);
}
