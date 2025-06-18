<?php

namespace Service;

use Predis\Client;

/**
 * Manages turn order and timer scheduling.
 */
class TurnManager
{
    private Client $redis;
    /** @var callable */
    private $broadcaster;

    /**
     * @param Client  $redis       Redis client
     * @param callable $broadcaster function(string $roomId, string $event, array $data): void
     */
    public function __construct(Client $redis, callable $broadcaster)
    {
        $this->redis = $redis;
        $this->broadcaster = $broadcaster;
    }

    /**
     * Initialize turn order and start the first turn.
     */
    public function startTurn(string $roomId): ?string
    {
        $turnOrderKey = "room:{$roomId}:turn_order";
        $current = $this->redis->lindex($turnOrderKey, 0);
        if ($current === null) {
            $users = $this->redis->smembers("room:{$roomId}:users");
            foreach ($users as $uid) {
                $this->redis->rpush($turnOrderKey, $uid);
            }
            $current = $this->redis->lindex($turnOrderKey, 0);
        }
        if ($current !== null) {
            $this->redis->hset("room:{$roomId}:turn", "current_turn_user_id", $current);
            $this->redis->setex("room:{$roomId}:turn_timer", 30, $current);
            ($this->broadcaster)($roomId, 'turn.started', ['userId' => $current]);
        }
        return $current;
    }

    /**
     * Rotate to the next user and reset timer.
     */
    public function rotateTurn(string $roomId): ?string
    {
        $turnOrderKey = "room:{$roomId}:turn_order";
        $next = $this->redis->rpoplpush($turnOrderKey, $turnOrderKey);
        if ($next === null) {
            return null;
        }
        $this->redis->hset("room:{$roomId}:turn", "current_turn_user_id", $next);
        $this->redis->setex("room:{$roomId}:turn_timer", 30, $next);
        ($this->broadcaster)($roomId, 'turn.started', ['userId' => $next]);
        return $next;
    }
}
