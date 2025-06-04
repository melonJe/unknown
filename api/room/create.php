<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/db.php';

try {
    $redis = getRedis();

    $mapId = 'default';
    $stmt = $pdo->prepare('SELECT board FROM map WHERE map_id = :map_id');
    $stmt->execute(['map_id' => $mapId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception("맵 데이터가 존재하지 않습니다: {$mapId}");
    }

    $defaultBoard = json_decode($row['board'], true);

    $roomId = substr(md5(uniqid()), 0, 10);
    $roomKey = "room:{$roomId}";
    $createdAt = date('Y-m-d H:i:s');

    $redis->hmset($roomKey, [
        'room_id' => $roomId,
        'board' => json_encode($defaultBoard, JSON_UNESCAPED_UNICODE),
        'state' => false,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $redis->expire($roomKey, 60 * 60 * 24);

    echo json_encode(["room_id" => $roomId]);
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "DB 또는 Redis 오류",
        "message" => $e->getMessage()
    ]);
}
