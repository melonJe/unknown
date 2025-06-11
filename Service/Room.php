<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

use Exception;

class Room
{
    public static function cleanupRooms(): array
    {
        $redis = getRedis();
        $keptRoomIds = [];
        $roomIds = [];
        $it = 0;
        $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        do {
            $roomIds = [];

            list($it, $roomKeys) = $redis->scan($it, ['match' => "room:*", 'count' => 100,]);

            if (!empty($roomKeys)) {
                foreach ($roomKeys as $key) {
                    $parts = explode(':', $key);
                    if (count($parts) === 2 && $parts[0] === 'room') {
                        $roomIds[] = $parts[1];
                    }
                }
            }
            foreach ($roomIds as $roomId) {
                $userCount = $redis->scard("room:{$roomId}:users");
                $roomData = $redis->hgetall("room:{$roomId}");
                if ($userCount === 0 && $roomData["updated_at"] < $fiveMinutesAgo) {
                    $redis->srem('rooms', $roomId);
                    $redis->del("room:{$roomId}");
                    $redis->del("room:{$roomId}:turn");
                    $redis->del("room:{$roomId}:users");

                    $subIt = 0;
                    do {
                        list($subIt, $keys) = $redis->scan($subIt, ['match' => "room:{$roomId}:user:*", 'count' => 100,]);
                        if (!empty($keys)) {
                            $redis->del($keys);
                        }
                    } while ($subIt != 0 && $subIt !== null);
                } else {
                    $keptRoomIds[] = $roomId;
                    continue;
                }
            }
        } while ($it != 0 && $it !== null);
        return $keptRoomIds;
    }

    public static function getRooms(): array
    {
        return self::cleanupRooms();
    }

    public static function createRoom($userId): string
    {
        $redis = getRedis();
        $pdo = getPdo();

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

        $tileMap = null;
        foreach ($defaultBoard["tiles"] as $tile) {
            $tileMap[$tile['x']][$tile['y']] = [
                "type" => $tile["type"],
                "score" => $tile["score"],
                "score" => $tile["score"],
                "color" => isset($tile['color']) && $tile['color'] !== null ? $tile['color'] : null,
            ];
        }

        $redis->hmset($roomKey, [
            'room_id' => $roomId,
            'state' => false,
            'width' => $defaultBoard['width'],
            'height' => $defaultBoard['height'],
            'tiles' => json_encode($tileMap, JSON_UNESCAPED_UNICODE),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $redis->expire($roomKey, 1800);
        $redis->sadd("room:{$roomId}:users", $userId);
        $redis->expire("room:{$roomId}:users", 60 * 60 * 24);
        return $roomId;
    }

    public static function getBoard($roomId): array
    {
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

        $tiles = [];
        foreach (json_decode($roomData['tiles'], true) as $xKey => $row) {
            foreach ($row as $yKey => $tile) {
                $tiles[] = [
                    "x" => $xKey,
                    "y" => $yKey,
                    "type" => $tile['type'],
                    "score" => $tile['score'],
                    "color" => isset($tile['color']) && $tile['color'] !== null ? $tile['color'] : null,
                ];
            }
        }

        return [
            "tiles" => $tiles,
            "width" => $roomData['width'],
            "height" => $roomData['height'],
        ];
    }

    public static function joinGame($userId, $roomId): void
    {
        if (!$roomId || !$userId) {
            http_response_code(400);
            exit;
        }

        $redis  = getRedis();
        $redis->sadd("room:{$roomId}:users", $userId);
        $redis->expire("room:{$roomId}:users", 60 * 60 * 24);
        $userKey = "room:{$roomId}:user:{$userId}";
        $userData = $redis->hgetall($userKey);

        if (!empty($userData)) {
            $redis->expire($userKey, 1800);
            return;
        }

        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            http_response_code(404);
            exit;
        }

        $tiles = json_decode($roomData['tiles'] ?? '', true);

        $startTiles = [];
        foreach ($tiles as $xKey => $row) {
            foreach ($row as $yKey => $tile) {
                if (($tile['type'] ?? '') === 'start') {
                    $startTiles[] = ['x' => $xKey, 'y' => $yKey];
                }
            }
        }

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
    }
}
