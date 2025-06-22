<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

class Turn
{
    public function getCurrentTurn(string $roomId): array
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order";
        $item  = $redis->lIndex($key, 0);

        return $item ? json_decode($item, true) : [];
    }

    /**
     * Get full turn order list.
     *
     * @param string $roomId
     * @return array<int,array<string,mixed>>
     */
    public function getTurnOrder(string $roomId): array
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order";
        $items = $redis->lRange($key, 0, -1);

        return array_map(static function ($v) {
            return json_decode($v, true);
        }, $items ?: []);
    }

    /**
     * Remove the current turn entry and append the next one.
     *
     * @param string                     $roomId
     * @param array<string,string|null>  $nextTurn
     * @return array<int,array<string,mixed>>
     */
    public function advanceTurn(string $roomId, array $nextTurn): array
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order";

        $redis->lPop($key);
        $redis->rPush($key, json_encode($nextTurn));

        return $this->getTurnOrder($roomId);
    }

    /**
     * Check whether all users have finished their start tile setup.
     */
    public function isSetupComplete(string $roomId): bool
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order";
        $len   = $redis->lLen($key);

        for ($i = 0; $i < $len; $i++) {
            $item = json_decode($redis->lIndex($key, $i), true);
            if (($item['action'] ?? '') === 'setStartTile') {
                return false;
            }
        }

        return true;
    }

    /**
     * Reorder turn list by the start tile score of each user.
     * Returns the newly ordered list.
     *
     * @param string $roomId
     * @return array<int,array<string,mixed>>
     */
    public function reorderByStartScore(string $roomId): array
    {
        $redis   = getRedis();
        $users   = $redis->smembers("room:{$roomId}:users");
        $scores  = [];

        foreach ($users as $uid) {
            $data           = $redis->hgetall("room:{$roomId}:user:{$uid}");
            $scores[$uid]   = isset($data['start_score']) ? (int)$data['start_score'] : PHP_INT_MAX;
        }

        asort($scores);

        $orderKey = "room:{$roomId}:turn_order";
        $redis->del($orderKey);
        $result = [];

        foreach (array_keys($scores) as $uid) {
            $entry = ['user' => $uid, 'action' => 'move'];
            $redis->rPush($orderKey, json_encode($entry));
            $result[] = $entry;
        }

        return $result;
    }
}
