<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';
require_once LIB_PATH . '/redis.php';

$redis = getRedis();

// 모든 방 목록 가져오기 (Set으로 관리한다고 가정: SADD rooms {room_id})
$roomIds = $redis->smembers('rooms');
$keptRoomIds = [];

foreach ($roomIds as $roomId) {
    // 각 방에 유저가 있는지 확인 (Set: room:{room_id}:users)
    $userCount = $redis->scard("room:{$roomId}:users");
    if ($userCount > 0) {
        $keptRoomIds[] = $roomId;
        continue;
    }
    // 유저 없으면 방 정보 삭제
    $redis->del("room:{$roomId}");
    $redis->srem('rooms', $roomId);
    // (옵션) 관련된 다른 키(턴, 기록 등)도 함께 삭제 가능
    $redis->del("room:{$roomId}:turn");
    // (옵션) room:{roomId}:user:* 모두 삭제하려면 scan + del 필요
    $pattern = "room:{$roomId}:user:*";
    $it = null;
    while ($keys = $redis->scan($it, $pattern)) {
        foreach ($keys as $key) {
            $redis->del($key);
        }
        if ($it === 0) {
            break;
        }
    }
}

echo json_encode(['rooms' => $keptRoomIds], JSON_UNESCAPED_UNICODE);
