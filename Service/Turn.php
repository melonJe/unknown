<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

class Turn
{
    public static function getHiddenOrder(string $roomId): array
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order_hidden";
        $items = $redis->lRange($key, 0, -1) ?: [];

        return array_map(
            static fn(string $json): array => [
                'user'   => ($t = json_decode($json, true))['user'],
                'action' => $t['action'],
            ],
            $items
        );
    }

    /**
     * move 리스트 전체를 ['user'=>…, 'action'=>…] 형태로 반환
     */
    public static function getMoveOrder(string $roomId): array
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order_move";
        $items = $redis->lRange($key, 0, -1) ?: [];

        return array_map(
            static fn(string $json): array => [
                'user'   => ($t = json_decode($json, true))['user'],
                'action' => $t['action'],
            ],
            $items
        );
    }

    public function getCurrentTurn(string $roomId): array
    {
        // 1) hidden 우선
        $hidden = self::getHiddenOrder($roomId);
        if (!empty($hidden)) {
            return $hidden[0];
        }
        // 2) 없으면 move
        $move = self::getMoveOrder($roomId);
        return $move[0] ?? [];
    }

    public static function getTurnOrder(string $roomId): array
    {
        // hidden + move 순으로 합쳐서 반환
        return array_merge(
            self::getHiddenOrder($roomId),
            self::getMoveOrder($roomId)
        );
    }

    /**
     * Remove the current turn entry and append the next one.
     *
     * @param string                     $roomId
     * @param array<string,string|null>  $nextTurn
     * @return array<int,array<string,mixed>>
     */
    public function advanceHiddenTurn(string $roomId, array $nextTurn): array
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order_hidden";

        // ① 기존 엔트리 로드
        $items = $redis->lRange($key, 0, -1) ?: [];

        // ② 같은 user, action이 이미 있는지 검사
        foreach ($items as $json) {
            $turn = json_decode($json, true);
            if (
                isset($turn['user'], $turn['action'])
                && $turn['user'] === $nextTurn['user']
                && $turn['action'] === $nextTurn['action']
            ) {
                // 중복이 있으면 추가하지 않고 현 상태 반환
                return $this->getTurnOrder($roomId);
            }
        }

        // ③ 중복 없으면 새 턴 추가
        $redis->rPush($key, json_encode($nextTurn));

        return $this->getTurnOrder($roomId);
    }

    public function advanceMoveTurn(string $roomId, array $nextTurn): array
    {
        $redis = getRedis();
        $key   = "room:{$roomId}:turn_order_move";

        $redis->lPop($key);
        $redis->rPush($key, json_encode($nextTurn));

        return $this->getTurnOrder($roomId);
    }
}
