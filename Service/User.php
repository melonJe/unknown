<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';

class User
{
    public static function getUserData($userId, $roomId)
    {
        $redis  = getRedis();
        $userKey = "room:{$roomId}:user:{$userId}";
        $redis->expire($userKey, 60 * 60 * 24);
        $redis->expire("room:{$roomId}:users", 60 * 60 * 24);
        return $redis->hgetall($userKey);
    }

    public static function getUserinRoom(): array
    {
        return room::cleanupRooms();
    }
    public static function deleteUserData($userId,  $roomId): void
    {
        $redis = getRedis();

        if (empty($userId)) {
            return;
        }
        $redis->del("user:{$userId}");
        $redis->srem("users", $userId);
        if (empty($roomId)) {
            return;
        }
        $redis->expire("room:{$roomId}:user:{$userId}", 60);
        $redis->srem("room:{$roomId}:users", $userId);
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
}
