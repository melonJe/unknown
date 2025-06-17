<?php

namespace DAO;

use Predis\Client;
use DTO\UserDto;

class UserDao
{
    private Client $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @param string $roomId
     * @param string $userId
     * @return UserDto|null
     */
    public function findByRoomAndUserId(string $roomId, string $userId): ?UserDto
    {
        $key  = $this->getRedisKey($roomId, $userId);
        $data = $this->redis->hgetall($key);

        if (empty($data)) {
            return null;
        }

        return UserDto::fromRedis($data);
    }

    /**
     * DTO를 Redis에 저장 (create/update)
     *
     * @param UserDto $user
     * @return void
     */
    public function save(UserDto $user): void
    {
        $key   = $this->getRedisKey($user->getRoomId(), $user->getUserId());
        $hash  = $user->toRedis();
        $this->redis->hmset($key, $hash);
    }

    /**
     * @param string $roomId
     * @param string $userId
     * @return void
     */
    public function delete(string $roomId, string $userId): void
    {
        $key = $this->getRedisKey($roomId, $userId);
        $this->redis->del([$key]);
    }

    /**
     * @param string $roomId
     * @param string $userId
     * @return string
     */
    private function getRedisKey(string $roomId, string $userId): string
    {
        return "room:{$roomId}:user:{$userId}";
    }
}
