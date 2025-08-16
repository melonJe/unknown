<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';

use DAO\UserDao;

class User
{
    public static function getUserData($roomId, $userId)
    {
        $redis   = getRedis();
        $dao     = new UserDao($redis);
        $redis->expire("room:{$roomId}:users", 60 * 60 * 24);
        $dto = $dao->findByRoomAndUserId($roomId, $userId);
        return $dto ? $dto->toArray() : [];
    }

    public static function getUserinRoom(): array
    {
        return room::cleanupRooms();
    }
    public static function deleteUserData($userId,  $roomId): void
    {
        $redis = getRedis();
        $dao   = new UserDao($redis);

        if (empty($userId)) {
            return;
        }
        $redis->del("user:{$userId}");
        $redis->srem("users", $userId);

        if (!empty($roomId)) {
            // Ensure the user is removed from any pending turn orders
            try {
                $turnSvc = new Turn();
                $turnSvc->removeUserFromTurns($roomId, $userId);
            } catch (\Throwable $e) {
                // ignore turn cleanup failure to avoid blocking disconnect
            }
            $dao->delete($roomId, $userId);
            $redis->srem("room:{$roomId}:users", $userId);
        }
    }

    public static function getDices($roomId): array
    {
        $player = [];
        if (!$roomId) {
            http_response_code(400);
            return ['error' => 'room_id and user_id are required'];
            exit;
        }

        $redis = getRedis();
        $dao   = new UserDao($redis);

        $users = $dao->findAllByRoomId($roomId);
        foreach ($users as $uid => $dto) {
            $player[$uid] = [
                'pos_x' => $dto->getPosX(),
                'pos_y' => $dto->getPosY(),
                'dice'  => $dto->getDice()->toArray(),
            ];
        }

        return ["player" => $player];
    }
}