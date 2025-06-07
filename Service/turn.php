<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

class Turn
{
    /**
     * 현재 사용자가 자신의 차례인지 확인하고 JSON으로 결과 반환
     */
    public static function isMyTurn(): bool
    {
        $redis = getRedis();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $myUserId = $_SESSION['user_id'] ?? null;

        if (!$myUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in'], JSON_UNESCAPED_UNICODE);
            return false;
        }

        $roomId   = User::getRoomIdByUser($myUserId);

        if (!$roomId) {
            http_response_code(400);
            echo json_encode(['error' => 'room_id is required'], JSON_UNESCAPED_UNICODE);
            return false;
        }

        $roomKey = "room:{$roomId}";

        $roomData = $redis->hgetall($roomKey);
        if (empty($roomData)) {
            http_response_code(404);
            echo json_encode(['error' => 'room not found'], JSON_UNESCAPED_UNICODE);
            return false;
        }

        // roomData['state']가 false(게임 시작 전)이면 항상 true로 반환
        if (isset($roomData['state']) && $roomData['state'] === 'false') {
            echo json_encode(['is_my_turn' => true], JSON_UNESCAPED_UNICODE);
            return true;
        }

        $turnUserId = $redis->hget("room:{$roomId}:turn", "current_turn_user_id");
        if (!$turnUserId) {
            http_response_code(404);
            echo json_encode(['error' => 'Turn info not found'], JSON_UNESCAPED_UNICODE);
            return false;
        }

        return $myUserId === $turnUserId;
    }

    /**
     * 다음 턴 유저로 변경하고 JSON으로 결과 반환
     */
    public static function nextUser(): void
    {
        $roomId = $_POST['room_id'] ?? null;

        if (!$roomId) {
            http_response_code(400);
            echo json_encode(['error' => 'room_id is required'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $redis = getRedis();

        // 턴 순서 리스트에서 사용자 ID 배열 조회
        $userIds = $redis->lrange("room:{$roomId}:turn_order", 0, -1);
        if (empty($userIds)) {
            http_response_code(404);
            echo json_encode(['error' => 'No turn order found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $currentTurnUser = $redis->hget("room:{$roomId}:turn", "current_turn_user_id");

        $currentIndex = array_search($currentTurnUser, $userIds, true);
        $nextIndex    = ($currentIndex === false)
            ? 0
            : ($currentIndex + 1) % count($userIds);

        $nextUserId = $userIds[$nextIndex];

        $redis->hset("room:{$roomId}:turn", "current_turn_user_id", $nextUserId);

        echo json_encode(['next' => $nextUserId], JSON_UNESCAPED_UNICODE);
    }
}
