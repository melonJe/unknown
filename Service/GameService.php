<?php

namespace Service;

use Predis\Client;
use DTO\RoomDto;
use DTO\UserDto;
use DAO\RoomDao;
use DAO\UserDao;

class GameService
{
    private Client $redis;
    private RoomDao $roomDao;
    private UserDao $userDao;
    private RuleEngine $ruleEngine;

    public function __construct(Client $redis, RoomDao $roomDao, UserDao $userDao, RuleEngine $ruleEngine)
    {
        $this->redis = $redis;
        $this->roomDao = $roomDao;
        $this->userDao = $userDao;
        $this->ruleEngine = $ruleEngine;
    }

    /**
     * Start a turn for the given room. Sets timeout key and broadcasts event.
     */
    public function startTurn(string $roomId): void
    {
        $turnOrderKey = "room:{$roomId}:turn_order";
        $current = $this->redis->lindex($turnOrderKey, 0);
        if ($current === null) {
            $users = $this->userDao->findAllByRoomId($roomId);
            foreach (array_keys($users) as $uid) {
                $this->redis->rpush($turnOrderKey, $uid);
            }
            $current = $this->redis->lindex($turnOrderKey, 0);
        }

        if ($current === null) {
            return;
        }

        $this->redis->hset("room:{$roomId}:turn", "current_turn_user_id", $current);
        $this->redis->setex("room:{$roomId}:turn_timer", 30, $current);
        $this->broadcast($roomId, 'TurnStarted', ['userId' => $current]);
    }

    /**
     * Handle a move action from a player and update states
     */
    public function playMove(string $roomId, string $userId, string $direction): void
    {
        $current = $this->redis->hget("room:{$roomId}:turn", "current_turn_user_id");
        if ($current !== $userId) {
            throw new \RuntimeException('Not your turn');
        }

        $room = $this->roomDao->findByRoomId((int)$roomId);
        $user = $this->userDao->findByRoomAndUserId($roomId, $userId);
        $all  = $this->userDao->findAllByRoomId($roomId);

        if (!$room || !$user) {
            throw new \RuntimeException('Invalid game state');
        }

        if (!$this->ruleEngine->validateMove($room, $user, $direction, $all)) {
            throw new \RuntimeException('Move blocked by rules');
        }

        // 실제 이동 로직은 간단히 주사위 앞면 색 기준 한 칸 이동만 처리
        $dx = 0; $dy = 0;
        switch ($direction) {
            case 'up':    $dy = -1; break;
            case 'down':  $dy = 1;  break;
            case 'left':  $dx = -1; break;
            case 'right': $dx = 1;  break;
        }

        $newX = $user->getPosX() + $dx;
        $newY = $user->getPosY() + $dy;

        $updated = new UserDto(
            $user->getRoomId(),
            $user->getUserId(),
            $newX,
            $newY,
            $user->getExileMarkCount(),
            $user->getDice(),
            $user->getJoinedAt()
        );
        $this->userDao->save($updated);

        $this->broadcast($roomId, 'DiceMoved', [
            'userId' => $userId,
            'x' => $newX,
            'y' => $newY,
        ]);

        $this->handleTimeout($roomId);
    }

    /**
     * Rotate turn when timer expires
     */
    public function handleTimeout(string $roomId): void
    {
        $turnOrderKey = "room:{$roomId}:turn_order";
        $next = $this->redis->rpoplpush($turnOrderKey, $turnOrderKey);
        if ($next === null) {
            return;
        }
        $this->redis->hset("room:{$roomId}:turn", "current_turn_user_id", $next);
        $this->redis->setex("room:{$roomId}:turn_timer", 30, $next);
        $this->broadcast($roomId, 'TurnStarted', ['userId' => $next]);
    }

    private function broadcast(string $roomId, string $type, array $payload): void
    {
        $channel = "room:{$roomId}:events";
        $data = json_encode(['type' => $type, 'payload' => $payload]);
        $this->redis->publish($channel, $data);
    }
}

