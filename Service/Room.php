<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

use DAO\RoomDao;
use DTO\RoomDto;

use Exception;

class Room
{
    public static function getRoomData($roomId)
    {
        $redis  = getRedis();
        $dao    = new RoomDao($redis);
        $room   = $dao->findByRoomId((int)$roomId);
        if ($room) {
            return $room->toArray();
        }

        return [];
    }

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
                "color" => isset($tile['color']) && $tile['color'] !== null ? $tile['color'] : null,
            ];
        }

        $redis->hmset($roomKey, [
            'room_id' => $roomId,
            'started' => '0',
            'width' => $defaultBoard['width'],
            'height' => $defaultBoard['height'],
            'tiles' => json_encode($tileMap, JSON_UNESCAPED_UNICODE),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $redis->expire($roomKey, 60 * 60 * 24);
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

    public static function joinGame($userId, $roomId): bool
    {
        if (!$roomId || !$userId) {
            http_response_code(400);
            return false;
        }

        $redis  = getRedis();
        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            http_response_code(404);
            return false;
        }

        $tiles = json_decode($roomData['tiles'] ?? '', true);
        $startCount = 0;
        foreach ($tiles as $row) {
            foreach ($row as $tile) {
                if (($tile['type'] ?? '') === 'start') {
                    $startCount++;
                }
            }
        }

        $currentPlayers = $redis->scard("room:{$roomId}:users");
        if ($currentPlayers >= $startCount) {
            return false;
        }

        $redis->sadd("room:{$roomId}:users", $userId);
        $redis->expire("room:{$roomId}:users", 60 * 60 * 24);
        $userKey = "room:{$roomId}:user:{$userId}";
        $userData = $redis->hgetall($userKey);

        if (!empty($userData)) {
            $redis->expire($userKey, 60 * 60 * 24);
            return true;
        }

        $diceArr = [
            'top'    => 'red',
            'bottom' => 'blue',
            'left'   => 'green',
            'right'  => 'yellow',
            'front'  => 'white',
            'back'   => 'purple',
        ];

        $redis->hmset($userKey, [
            'pos_x'     => -1,
            'pos_y'     => -1,
            'dice'      => json_encode($diceArr),
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
        $redis->expire($userKey, 60 * 60 * 24);
        return true;
    }

    public static function startGame(string $roomId): array
    {
        $redis    = getRedis();
        $roomKey  = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            return [];
        }

        if (($roomData['started'] ?? '0') === '1') {
            return ['error' => 'already_started'];
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

        $userIds = $redis->smembers("room:{$roomId}:users");

        if (count($userIds) < 4 || count($userIds) > count($startTiles)) {
            return ['error' => 'invalid_player_count'];
        }

        $redis->hset($roomKey, 'started', '1');
        $redis->hset($roomKey, 'updated_at', date('Y-m-d H:i:s'));
        $turnOrder = $userIds;
        shuffle($turnOrder);
        $turnKey = "room:{$roomId}:turn_order";
        $redis->del($turnKey);
        foreach ($turnOrder as $uid) {
            $redis->rpush($turnKey, $uid);
        }
        if (!empty($turnOrder)) {
            $redis->hset("room:{$roomId}:turn", 'current_turn_user_id', $turnOrder[0]);
        }

        if (!empty($startTiles)) {
            foreach ($userIds as $uid) {
                $userKey = "room:{$roomId}:user:{$uid}";
                $redis->hmset($userKey, [
                    'pos_x' => -1,
                    'pos_y' => -1,
                ]);
                $redis->expire($userKey, 60 * 60 * 24);
            }
        }

        return ['turn_order' => $turnOrder];
    }

    public static function setStartTile(string $roomId, string $userId, int $x, int $y, array $dice): bool
    {
        $redis   = getRedis();
        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);
        if (empty($roomData)) {
            return false;
        }
        $tiles = json_decode($roomData['tiles'] ?? '', true);
        if (!isset($tiles[$x][$y]) || ($tiles[$x][$y]['type'] ?? '') !== 'start') {
            return false;
        }
        $userIds = $redis->smembers("room:{$roomId}:users");
        foreach ($userIds as $uid) {
            $u = $redis->hgetall("room:{$roomId}:user:{$uid}");
            if ($u && (int)($u['pos_x'] ?? -1) === $x && (int)($u['pos_y'] ?? -1) === $y) {
                return false;
            }
        }
        $userKey = "room:{$roomId}:user:{$userId}";
        $redis->hmset($userKey, [
            'pos_x' => $x,
            'pos_y' => $y,
            'dice'  => json_encode($dice),
        ]);
        $redis->expire($userKey, 60 * 60 * 24);
        return true;
    }
}
