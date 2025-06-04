<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php';

use Helpers\DiceHelper;

session_start();

$roomId = $_POST['room_id'] ?? null;
$direction = $_POST['direction'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$roomId || !$direction || !$userId) {
    http_response_code(400);
    echo json_encode(["error" => "room_id, direction, user_id required"]);
    exit;
}

$redis = getRedis();

// 1. 턴 체크
// $turnUser = $redis->hget("room:{$roomId}:turn", "current_turn_user_id");
// if ($turnUser !== $userId) {
//     http_response_code(403);
//     echo json_encode(['error' => 'not your turn']);
//     exit;
// }

// 2. 보드/유저 상태 불러오기
$room = $redis->hgetall("room:{$roomId}");
if (!$room) {
    http_response_code(404);
    echo json_encode(["error" => "room not found"]);
    exit;
}
$board = json_decode($room['board'], true);
$tiles = $board['tiles'] ?? [];

$userIds = $redis->smembers("room:{$roomId}:users");
$userStates = [];
foreach ($userIds as $uid) {
    $userStates[$uid] = $redis->hgetall("room:{$roomId}:user:{$uid}");
    $userStates[$uid]['dice'] = json_decode($userStates[$uid]['dice'], true);
}

function posKey($x, $y)
{
    return "{$x},{$y}";
}

// 현재 유저 위치
$curr = $userStates[$userId];
$x = (int) $curr['pos_x'];
$y = (int) $curr['pos_y'];

// 방향
$dx = 0;
$dy = 0;
switch ($direction) {
    case 'up':
        $dx = 0;
        $dy = -1;
        break;
    case 'down':
        $dx = 0;
        $dy = 1;
        break;
    case 'left':
        $dx = -1;
        $dy = 0;
        break;
    case 'right':
        $dx = 1;
        $dy = 0;
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'invalid direction']);
        exit;
}

// 3. 타겟 위치, 보드 유효성
$nx = $x + $dx;
$ny = $y + $dy;

if (!DiceHelper::isValidTile($nx, $ny, $tiles)) {
    http_response_code(400);
    echo json_encode(['error' => 'destination invalid']);
    exit;
}

// 4. 현재 위치별로 유저 매핑
$posToUid = [];
foreach ($userStates as $uid => $state) {
    $posToUid[posKey($state['pos_x'], $state['pos_y'])] = $uid;
}

// 5. 재귀 밀어내기
function tryPush(&$userStates, $x, $y, $dx, $dy, $tiles, &$posToUid)
{
    $targetKey = "{$x},{$y}";
    if (!isset($posToUid[$targetKey])) {
        return true;
    }
    $uid = $posToUid[$targetKey];
    $nx = $x + $dx;
    $ny = $y + $dy;
    $nextKey = "{$nx},{$ny}";

    if (!DiceHelper::isValidTile($nx, $ny, $tiles)) {
        return false;
    }
    if (!tryPush($userStates, $nx, $ny, $dx, $dy, $tiles, $posToUid)) {
        return false;
    }

    // 이동: 위치, 주사위 회전
    $userStates[$uid]['pos_x'] = $nx;
    $userStates[$uid]['pos_y'] = $ny;
    $userStates[$uid]['dice'] = DiceHelper::roll($userStates[$uid]['dice'], $_POST['direction']);
    // 위치 매핑 갱신
    unset($posToUid[$targetKey]);
    $posToUid[$nextKey] = $uid;
    return true;
}

// 6. 내 이동 전에 미는게 가능한지 먼저 체크
if (!tryPush($userStates, $nx, $ny, $dx, $dy, $tiles, $posToUid)) {
    http_response_code(400);
    echo json_encode(['error' => 'cannot push into invalid tile']);
    exit;
}

// 7. 실제로 내 위치, 주사위 업데이트
$myNewDice = DiceHelper::roll($userStates[$userId]['dice'], $direction);
$userStates[$userId]['pos_x'] = $nx;
$userStates[$userId]['pos_y'] = $ny;
$userStates[$userId]['dice'] = $myNewDice;

// 8. (확장) 히든 룰 발동 체크 (여기서 추가 조건문/함수 연결)
// if (HiddenRuleHelper::shouldFire(...)) { ... }

// 9. Redis에 상태 저장
foreach ($userStates as $uid => $state) {
    $redis->hmset("room:{$roomId}:user:{$uid}", [
        'pos_x' => $state['pos_x'],
        'pos_y' => $state['pos_y'],
        'dice' => json_encode($state['dice'], JSON_UNESCAPED_UNICODE),
    ]);
}

// 10. 턴 넘기기: (여기선 예시로 round-robin)
// (실전에서는 room:{roomId}:turn 리스트에서 다음 유저 pop/push)
// $allTurnList = $redis->lrange("room:{$roomId}:turn_order", 0, -1);
// $curIdx = array_search($userId, $allTurnList);
// $nextIdx = ($curIdx + 1) % count($allTurnList);
// $nextUser = $allTurnList[$nextIdx];
// $redis->hset("room:{$roomId}:turn", "current_turn_user_id", $nextUser);

// 11. 골인/종료 조건 체크 등 후처리 필요(여기선 생략)

echo json_encode([
    'success' => true,
    'new_position' => ['x' => $nx, 'y' => $ny],
    'dice' => $myNewDice,
    'next_turn' => $nextUser,
]);
