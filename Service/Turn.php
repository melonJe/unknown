<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

class Turn
{
    public static function getHiddenOrder(string $roomId): array
    {
        $rule = new Rule();
        $redis = getRedis(); // Assuming getRedis() correctly returns a Redis client instance
        $key = "room:{$roomId}:turn_order_hidden";
        $items = $redis->lRange($key, 0, -1) ?: []; // Ensure $items is always an array

        $result = [];

        if (!empty($items)) {
            foreach ($items as $itemString) { // Renamed $item to $itemString for clarity
                $decodedItem = json_decode($itemString, true);

                // --- Potential problem lines were here (20, 24, 28) ---
                // Add a check to ensure $decodedItem is an array and not null
                if (is_array($decodedItem)) { // This is the crucial check
                    if ($decodedItem['action'] === 'setDiceState') {
                        // Line 20 (original)
                        if ($rule->isNoSameColorInNine($roomId, $decodedItem['user'])) {
                            $result[] = $decodedItem;
                        }
                    } elseif ($decodedItem['action'] === 'targetMove') {
                        // Line 24 (original)
                        if ($rule->isThreeOrMoreInNine($roomId, $decodedItem['user'])) {
                            $result[] = $decodedItem;
                        }
                    } elseif ($decodedItem['action'] === 'extraTurn') {
                        // Line 28 (original)
                        if ($rule->isLineOfThree($roomId, $decodedItem['user'])) {
                            $result[] = $decodedItem;
                        }
                    }
                } else {
                    // Optional: Log or handle cases where JSON decoding fails
                    error_log("Warning: Failed to decode JSON item from Redis for room '{$roomId}': {$itemString}");
                }
            }
        }

        $redis->del($key); // Clear the old key

        // If $result is not empty, re-push valid items back to Redis
        if (!empty($result)) {
            // Convert each item in $result back to JSON string for rPush
            $jsonResults = array_map('json_encode', $result);
            $redis->rPush($key, ...$jsonResults);
        }

        // Final mapping to the desired output format
        return array_map(
            static fn(array $item): array => [
                'user' => $item['user'],
                'action' => $item['action'],
            ],
            $result
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

    public function removeUserFromTurns(string $roomId, string $userId): void
    {
        $redis = getRedis();

        // Remove from hidden turn order
        $hiddenKey = "room:{$roomId}:turn_order_hidden";
        $currentHidden = $redis->lRange($hiddenKey, 0, -1) ?: [];
        $newHidden = [];
        foreach ($currentHidden as $json) {
            $turn = json_decode($json, true);
            if (!isset($turn['user']) || $turn['user'] !== $userId) {
                $newHidden[] = $json;
            }
        }
        // Clear the old list and push the filtered items back
        $redis->del($hiddenKey);
        if (!empty($newHidden)) {
            $redis->rPush($hiddenKey, ...$newHidden);
        }

        // Remove from move turn order
        $moveKey = "room:{$roomId}:turn_order_move";
        $currentMove = $redis->lRange($moveKey, 0, -1) ?: [];
        $newMove = [];
        foreach ($currentMove as $json) {
            $turn = json_decode($json, true);
            if (!isset($turn['user']) || $turn['user'] !== $userId) {
                $newMove[] = $json;
            }
        }
        // Clear the old list and push the filtered items back
        $redis->del($moveKey);
        if (!empty($newMove)) {
            $redis->rPush($moveKey, ...$newMove);
        }
    }
}