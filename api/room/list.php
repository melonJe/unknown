<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php';

$redis = getRedis();

// 모든 방 목록 가져오기
$roomIds = $redis->smembers('rooms');
$keptRoomIds = [];
file_put_contents('php://stdout', json_encode($roomIds));
foreach ($roomIds as $roomId) {
    // 각 방에 유저가 있는지 확인
    $userCount = $redis->scard("room:{$roomId}:users");
    if ($userCount > 0) {
        $keptRoomIds[] = $roomId;
        continue;
    }
    // 유저 없으면 방 정보 삭제
    $redis->del("room:{$roomId}");
    $redis->srem('rooms', $roomId);
    $redis->del("room:{$roomId}:turn");

    // room:{roomId}:users:* 형식의 모든 키 삭제 (scan + del)
    $pattern = "room:{$roomId}:user:*";
    $it = null;
    do {
        list($it, $keys) = $redis->scan($it, ['match' => $pattern, 'count' => 100]);
        if (!empty($keys)) {
            $redis->del(...$keys);
        }
    } while ($it != 0 && $it !== null);
}

echo json_encode(['rooms' => $keptRoomIds], JSON_UNESCAPED_UNICODE);
