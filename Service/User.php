<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';

class User
{
    /**
     * 현재 세션의 user_id 반환 (로그인되어 있지 않으면 401 에러)
     */
    public static function getMeId(): array
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            return ['error' => 'not initialized'];
        }

        return ['user_id' => $_SESSION['user_id']];
    }

    /**
     * 역방향 인덱싱: user_id에서 room_id 조회
     */
    public static function getRoomIdByUser(string $userId): array
    {
        $redis = getRedis();
        $result = [];
        $it = 0;
        do {
            list($it, $roomKeys) = $redis->scan($it, ['match' => "room:*:user:{$userId}", 'count' => 100,]);
            // var_dump($roomKeys);

            if (!empty($roomKeys)) {
                foreach ($roomKeys as $key) {
                    $parts = explode(':', $key);
                    if (count($parts) === 2 && $parts[0] === 'room') {
                        $result[] = $parts[1];
                    }
                }
            }
        } while ($it != 0 && $it !== null);
        return $result;
    }

    /**
     * 연결 해제 시 호출: 유저 데이터 및 턴 리스트 정리
     */
    public static function deleteUserData(string $userId, string $roomId): void
    {
        $redis = getRedis();

        $redis->del("user:{$userId}");
        $redis->srem("users", $userId);
        $redis->expire("room:{$roomId}:user:{$userId}", 60);
        $redis->srem("room:{$roomId}:users", $userId);
        // 웹소켓 브로드캐스트는 서버 로직에서 처리하세요
    }

    /**
     * 최근 5분 내에 활동한 유저 리스트를 반환
     */
    public static function getUsersId(): array
    {
        $redis        = getRedis();
        $activeSince  = strtotime('-5 minutes');
        $allUserIds   = $redis->smembers('users') ?: [];
        $activeUsers  = [];

        foreach ($allUserIds as $uid) {
            $userKey = "user:{$uid}";
            $user    = $redis->hgetall($userKey);
            if (!$user) {
                continue;
            }
            $activeAt = strtotime($user['last_active'] ?? '1970-01-01 00:00:00');
            if ($activeAt >= $activeSince) {
                $activeUsers[] = [
                    'user_id'     => $user['user_id'],
                    'nickname'    => $user['nickname'],
                    'last_active' => $user['last_active'],
                ];
            }
        }

        usort($activeUsers, fn($a, $b) => strtotime($b['last_active']) <=> strtotime($a['last_active']));

        return ['users' => $activeUsers];
    }
    public static function getDices($roomId): array
    {
        $player = [];
        if (!$roomId) {
            http_response_code(400);
            return ['error' => 'room_id and user_id are required'];
            exit;
        }

        $redis  = getRedis();
        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            http_response_code(404);
            return ['error' => 'room not found'];
            exit;
        }

        $subIt = 0;
        do {
            list($subIt, $keys) = $redis->scan($subIt, ['match' => "room:{$roomId}:user:*", 'count' => 100,]);
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $userId = null;
                    $parts = explode(':', $key);
                    if (count($parts) === 4 && $parts[2] === 'user') {
                        $userId = $parts[3];
                    }

                    $userData = $redis->hgetall($key);
                    if (!empty($userData['pos_x']) && !empty($userData['pos_y'])) {
                        $player += [
                            $userId => [
                                'pos_x'   => $userData['pos_x'],
                                'pos_y'   => $userData['pos_y'],
                                'dice'    => json_decode($userData['dice'] ?? '{}', true),
                            ]
                        ];
                    }
                }
            }
        } while ($subIt != 0 && $subIt !== null);

        return ["player" => $player];
    }

    public static function joinGame($userId, $roomId): void
    {
        if (!$roomId || !$userId) {
            http_response_code(400);
            exit;
        }

        $redis  = getRedis();
        $userKey = "room:{$roomId}:user:{$userId}";
        $userData = $redis->hgetall($userKey);

        if (!empty($userData)) {
            return;
        }

        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            http_response_code(404);
            exit;
        }

        $board = json_decode($roomData['board'] ?? '', true);
        $tiles = $board['tiles'] ?? [];

        $startTiles = array_values(array_filter($tiles, fn($t) => ($t['type'] ?? '') === 'start'));

        if (empty($startTiles)) {
            http_response_code(500);
            exit;
        }

        $startTile = $startTiles[random_int(0, count($startTiles) - 1)];

        $diceArr = [
            'top'    => 'red',
            'bottom' => 'blue',
            'left'   => 'green',
            'right'  => 'yellow',
            'front'  => 'white',
            'back'   => 'purple',
        ];

        $redis->hmset($userKey, [
            'pos_x'     => $startTile['x'],
            'pos_y'     => $startTile['y'],
            'dice'      => json_encode($diceArr),
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
        $redis->expire($userKey, 1800);

        $redis->sadd("room:{$roomId}:users", $userId);
        $redis->expire("room:{$roomId}:users", 60 * 60 * 24);
    }
}
