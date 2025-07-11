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
     * 방에 존재하는 모든 유저 정보를 조회한다
     *
     * @param string $roomId
     * @return array<string,UserDto>
     */
    public function findAllByRoomId(string $roomId): array
    {
        $setKey  = "room:{$roomId}:users";
        $userIds = $this->redis->smembers($setKey);

        $users = [];
        foreach ($userIds as $uid) {
            $dto = $this->findByRoomAndUserId($roomId, $uid);
            if ($dto) {
                $users[$uid] = $dto;
            }
        }

        return $users;
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