<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

class Turn
{
    public function getCurrentTurn(string $roomId): array

    {
        $redis    = getRedis();
        $key = "room:{$roomId}:turn_order";
        $item = $redis->lIndex($key, 0);
        return $item ? json_decode($item, true) : [];
    }
}
