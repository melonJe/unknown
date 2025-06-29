<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

use DAO\RoomDao;
use Service\Board;
use Helpers\DiceHelper;
use Exception;
use Service\Response;

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
        $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-1 minutes'));
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
                    $redis->del("room:{$roomId}:turn_order_hidden");
                    $redis->del("room:{$roomId}:turn_order_move");
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
        $redis = getRedis();
        $roomIds = self::cleanupRooms();
        $result = [];
        foreach ($roomIds as $roomId) {
            $roomKey = "room:{$roomId}";
            $roomData = $redis->hgetall($roomKey);
            $result[$roomId] = $roomData["started"];
        }
        return $result;
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
            return Response::error('room_id and user_id are required');
        }

        $redis  = getRedis();
        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            http_response_code(404);
            return Response::error('room not found');
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

        return Response::success([
            "tiles"   => $tiles,
            "started" => $roomData['started'],
            "width"   => $roomData['width'],
            "height"  => $roomData['height'],
        ]);
    }

    public static function joinGame($userId, $roomId): array
    {
        if (!$roomId || !$userId) {
            http_response_code(400);
            return Response::error('room_id and user_id required');
        }

        $redis  = getRedis();
        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            http_response_code(404);
            return Response::error('room not found');
        }

        if (($roomData['started'] ?? '0') === '1') {
            return Response::error('already_started');
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
            return Response::error('room_full');
        }

        $redis->sadd("room:{$roomId}:users", $userId);
        $redis->expire("room:{$roomId}:users", 60 * 60 * 24);
        $userKey = "room:{$roomId}:user:{$userId}";
        $userData = $redis->hgetall($userKey);

        if (!empty($userData)) {
            $redis->expire($userKey, 60 * 60 * 24);
            return Response::success(['message' => 'join']);
        }
        $startTiles = Board::getStartTiles($roomData['tiles']);

        if (empty($startTiles)) {
            http_response_code(500);
            return Response::error('invalid_board');
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
        $redis->expire($userKey, 60 * 60 * 24);

        return Response::success(['message' => 'join']);
    }

    public static function startGame(string $roomId): array
    {
        $redis    = getRedis();
        $roomKey  = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);

        if (empty($roomData)) {
            return Response::error('room not found');
        }

        if (($roomData['started'] ?? '0') === '1') {
            return Response::error('already_started');
        }

        $startTiles = Board::getStartTiles($roomData['tiles']);

        $userIds = $redis->smembers("room:{$roomId}:users");

        // if (count($userIds) < 4 || count($userIds) > count($startTiles)) {
        //     return ['error' => 'invalid_player_count'];
        // }

        $redis->hset($roomKey, 'started', '1');
        $redis->hset($roomKey, 'updated_at', date('Y-m-d H:i:s'));
        $turnOrder = $userIds;
        shuffle($turnOrder);
        $orderKey = "room:{$roomId}:turn_order_move";
        $redis->del($orderKey);
        $result = [];
        foreach ($turnOrder as $userId) {
            $entry = [
                'user'   => $userId,
                'action' => "setStartTile",
            ];
            $redis->rPush($orderKey, json_encode($entry));
            $result[] = [
                'user'   => $userId,
                'action' => "setStartTile",
            ];
        }

        if (!empty($startTiles)) {
            foreach ($userIds as $uid) {
                $userKey = "room:{$roomId}:user:{$uid}";
                $redis->hmset($userKey, [
                    'pos_x' => -1,
                    'pos_y' => -1,
                    'start_score' => 0,
                ]);
                $redis->expire($userKey, 60 * 60 * 24);
            }
        }

        return Response::success(['turn_order' => $result]);
    }

    public static function setStartTile(string $roomId, string $userId, int $x, int $y, array $dice): array
    {
        $redis   = getRedis();
        $roomKey = "room:{$roomId}";
        $roomData = $redis->hgetall($roomKey);
        if (empty($roomData)) {
            return Response::error('room not found');
        }
        $tiles = json_decode($roomData['tiles'] ?? '', true);
        if (!isset($tiles[$x][$y]) || ($tiles[$x][$y]['type'] ?? '') !== 'start') {
            return Response::error('invalid start');
        }
        $userIds = $redis->smembers("room:{$roomId}:users");
        foreach ($userIds as $uid) {
            $u = $redis->hgetall("room:{$roomId}:user:{$uid}");
            if ($u && (int)($u['pos_x'] ?? -1) === $x && (int)($u['pos_y'] ?? -1) === $y) {
                return Response::error('invalid start');
            }
        }
        $orientation = DiceHelper::orientationFromTopFront($dice['top'] ?? '', $dice['front'] ?? '');
        if (!$orientation) {
            return Response::error('invalid dice');
        }

        $startScore = (int)($tiles[$x][$y]['score'] ?? 0);

        $userKey = "room:{$roomId}:user:{$userId}";
        $redis->hmset($userKey, [
            'pos_x'       => $x,
            'pos_y'       => $y,
            'dice'        => json_encode($orientation),
            'start_score' => $startScore,
        ]);
        $redis->expire($userKey, 60 * 60 * 24);
        return Response::success();
    }
}
