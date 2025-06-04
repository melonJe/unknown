<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php';

$redis = getRedis();

// 최근 5분 기준 타임스탬프
$activeSince = strtotime('-5 minutes');

$userIds = $redis->smembers('users'); // 전체 유저 Set
$users = [];

foreach ($userIds as $userId) {
    $userKey = "user:{$userId}";
    $user = $redis->hgetall($userKey);

    if (!$user) {
        continue;
    }

    // last_active 파싱 (형식: Y-m-d H:i:s)
    $activeAt = strtotime($user['last_active'] ?? '1970-01-01 00:00:00');
    if ($activeAt >= $activeSince) {
        $users[] = [
            'user_id' => $user['user_id'],
            'nickname' => $user['nickname'],
            'last_active' => $user['last_active']
        ];
    }
}

// 최근순 정렬 (last_active 내림차순)
usort($users, function ($a, $b) {
    return strtotime($b['last_active']) <=> strtotime($a['last_active']);
});

echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
