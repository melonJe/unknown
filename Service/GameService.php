<?php

namespace Service;

use Predis\Client;
use DTO\UserDto;
use DAO\RoomDao;
use DAO\UserDao;

class GameService
{
    private Client $redis;
    private RoomDao $roomDao;
    private UserDao $userDao;
    private RuleEngine $ruleEngine;
    private TurnManager $turnManager;

    public function __construct(
        Client $redis,
        RoomDao $roomDao,
        UserDao $userDao,
        RuleEngine $ruleEngine,
        TurnManager $turnManager
    )
    {
        $this->redis = $redis;
        $this->roomDao = $roomDao;
        $this->userDao = $userDao;
        $this->ruleEngine = $ruleEngine;
        $this->turnManager = $turnManager;
    }

    /**
     * Start a turn for the given room.
     */
    public function startTurn(string $roomId): void
    {
        $this->turnManager->startTurn($roomId);
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

        $this->broadcastEvent($roomId, 'dice.moved', [
            'userId' => $userId,
            'x' => $newX,
            'y' => $newY,
        ]);
        $this->nextTurn($roomId);
    }

    /**
     * Rotate turn when timer expires
     */
    public function nextTurn(string $roomId): void
    {
        $this->turnManager->rotateTurn($roomId);
    }
    private function broadcastEvent(string $roomId, string $event, array $payload): void
    {
        $channel = "room:{$roomId}:events";
        $data = json_encode(['event' => $event, 'data' => $payload]);
        $this->redis->publish($channel, $data);
    }
}

