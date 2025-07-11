<?php

namespace Service;

use Service\Turn;
use Service\Rule;
use Service\Response;
use Helpers\DiceHelper;
use DAO\UserDao;
use DAO\RoomDao;
use DTO\UserDto;
use DTO\DiceDto;


class Dice
{
    public static function targetMove(string $roomId, string $userId, string $targetUserId, string $direction): array
    {
        $redis   = getRedis();
        $userDao = new UserDao($redis);
        $rule    = new Rule();

        $allUsers = $userDao->findAllByRoomId($roomId);
        $user     = $allUsers[$userId];

        $targetUser = $allUsers[$targetUserId];
        if ($user->getDice()->getFrontColor() !== 'yellow' && $user->getDice()->getFrontColor() !== $targetUser->getDice()->getTopColor()) {
            return Response::error('Invalid dice position. Same color found nearby.', 'targetMove');
        }

        if (!$roomId || !$direction || !$userId) {
            http_response_code(400);
            return Response::error('room_id, direction, user_id required');
        }

        $roomKey = "room:{$roomId}";
        $redis->expire($roomKey, 60 * 60 * 24);
        $roomData = $redis->hgetall($roomKey);

        if (!$roomData) {
            http_response_code(404);
            return Response::error('room not found');
        }
        $tiles = json_decode($roomData['tiles'], true);

        $userStates = [];
        $userDtos   = $userDao->findAllByRoomId($roomId);
        foreach ($userDtos as $uid => $dto) {
            $userStates[$uid] = [
                'pos_x' => $dto->getPosX(),
                'pos_y' => $dto->getPosY(),
                'dice'  => $dto->getDice()->toArray(),
            ];
        }

        $curr = $userStates[$targetUserId];
        $x = (int) $curr['pos_x'];
        $y = (int) $curr['pos_y'];
        // 방향 처리
        $dx = 0;
        $dy = 0;
        switch ($direction) {
            case 'up':
                $dy = -1;
                break;
            case 'down':
                $dy = 1;
                break;
            case 'left':
                $dx = -1;
                break;
            case 'right':
                $dx = 1;
                break;
            default:
                http_response_code(400);
                return Response::error('invalid direction');
        }

        $nx = $x + $dx;
        $ny = $y + $dy;

        if (!DiceHelper::isValidTile($nx, $ny, $tiles)) {
            http_response_code(400);
            return Response::error('destination invalid');
        }

        // 위치 → 유저 매핑
        $posToUid = [];
        foreach ($userStates as $uid => $state) {
            $posToUid[self::posKey($state['pos_x'], $state['pos_y'])] = $uid;
        }

        // 미는게 가능한지
        if (!self::tryPush($userStates, $nx, $ny, $dx, $dy, $tiles, $posToUid, $direction)) {
            http_response_code(400);
            return Response::error('cannot push into invalid tile');
        }

        // 내 주사위 이동
        $myNewDice = DiceHelper::roll($userStates[$targetUserId]['dice'], $direction);
        $userStates[$targetUserId]['pos_x'] = $nx;
        $userStates[$targetUserId]['pos_y'] = $ny;
        $userStates[$targetUserId]['dice']  = $myNewDice;

        $goalReached = false;
        foreach ($userStates as $state) {
            $t = $tiles[$state['pos_x']][$state['pos_y']]['type'] ?? '';
            if ($t === 'goal') {
                $goalReached = true;
                break;
            }
        }

        if ($goalReached) {
            $redis->hset($roomKey, 'finished', '1');
            $redis->del("room:{$roomId}:turn_order_move");
            $redis->del("room:{$roomId}:turn_order_move");
            return Response::success(['game_end'     => $goalReached]);
        }

        $userDtos = $userDao->findAllByRoomId($roomId);

        // Redis 저장
        foreach ($userStates as $uid => $state) {
            $orig = $userDtos[$uid] ?? null;
            if (!$orig) {
                continue;
            }
            $dto = new UserDto(
                $roomId,
                $uid,
                $state['pos_x'],
                $state['pos_y'],
                $orig->getExileMarkCount(),
                new DiceDto(
                    $state['dice']['top'],
                    $state['dice']['bottom'],
                    $state['dice']['left'],
                    $state['dice']['right'],
                    $state['dice']['front'],
                    $state["dice"]["back"],
                ),
                $orig->getJoinedAt()
            );
            $userDao->save($dto);
            $redis->expire("room:{$roomId}:user:{$uid}", 60 * 60 * 24);
        }

        self::applyHiddenRules($roomId, $userId);

        return Response::success(['message' => 'Dice state updated.']);
    }
    public static function setDiceState(string $roomId, string $userId, array $diceData): array
    {
        $redis   = getRedis();
        $userDao = new UserDao($redis);
        $rule    = new Rule();

        $allUsers = $userDao->findAllByRoomId($roomId);
        $user     = $allUsers[$userId];

        // Update user's dice temporarily to check the new state
        $orientation = DiceHelper::orientationFromTopFront($diceData['top'] ?? '', $diceData['front'] ?? '');
        if (!$orientation) {
            return Response::error('invalid dice');
        }
        $userKey = "room:{$roomId}:user:{$userId}";
        $redis->hmset($userKey, [
            'pos_x'       => $user->getPosX(),
            'pos_y'       => $user->getPosY(),
            'dice'        => json_encode($orientation),
        ]);
        $redis->expire($userKey, 60 * 60 * 24);
        if (!$rule->isNoSameColorInNine($roomId, $userId)) {
            // Valid state, save and advance turn
            $redis->lPop("room:{$roomId}:turn_order_hidden");
            return Response::success(['message' => 'Dice state updated.']);
        } else {
            // Invalid state, revert dice and ask user to try again
            return Response::error('Invalid dice position. Same color found nearby.', 'setDiceState');
        }
    }

    // 좌표를 key로 변환 (클래스 내부 유틸 함수로 선언)
    private static function posKey($x, $y)
    {
        return "{$x},{$y}";
    }

    // 재귀 밀어내기 함수 (클래스 static 메서드)
    private static function tryPush(&$userStates, $x, $y, $dx, $dy, $tiles, &$posToUid, $direction)
    {
        $targetKey = self::posKey($x, $y);
        if (!isset($posToUid[$targetKey])) {
            return true;
        }
        $uid = $posToUid[$targetKey];
        $nx = $x + $dx;
        $ny = $y + $dy;
        $nextKey = self::posKey($nx, $ny);

        if (!DiceHelper::isValidTile($nx, $ny, $tiles)) {
            return false;
        }
        if (!self::tryPush($userStates, $nx, $ny, $dx, $dy, $tiles, $posToUid, $direction)) {
            return false;
        }

        // 이동: 위치, 주사위 회전
        $userStates[$uid]['pos_x'] = $nx;
        $userStates[$uid]['pos_y'] = $ny;
        $userStates[$uid]['dice'] = DiceHelper::roll($userStates[$uid]['dice'], $direction);
        unset($posToUid[$targetKey]);
        $posToUid[$nextKey] = $uid;
        return true;
    }

    public static function move($userId, $roomId, $direction): array
    {
        if (!$roomId || !$direction || !$userId) {
            http_response_code(400);
            return Response::error('room_id, direction, user_id required');
        }

        $redis    = getRedis();
        $dao      = new UserDao($redis);
        $redis->expire("user:{$userId}", 60 * 60 * 24);
        $roomKey = "room:{$roomId}";
        $redis->expire($roomKey, 60 * 60 * 24);
        $roomData = $redis->hgetall($roomKey);


        //턴 체크 및 게임 시작 여부 확인
        $turnService = new Turn();
        $started  = isset($roomData['started']) && $roomData['started'] !== '0';
        if ($started) {
            $turnUser  = $turnService->getCurrentTurn($roomId);
            if ($turnUser['user'] !== $userId || !($turnUser['action'] === 'move' || $turnUser['action'] === 'extraTurn')) {
                http_response_code(403);
                return Response::error('not your turn');
            }
        }

        if (!$roomData) {
            http_response_code(404);
            return Response::error('room not found');
        }
        $tiles = json_decode($roomData['tiles'], true);

        $userStates = [];
        $userDtos   = $dao->findAllByRoomId($roomId);
        foreach ($userDtos as $uid => $dto) {
            $userStates[$uid] = [
                'pos_x' => $dto->getPosX(),
                'pos_y' => $dto->getPosY(),
                'dice'  => $dto->getDice()->toArray(),
            ];
        }

        $curr = $userStates[$userId];
        $x = (int) $curr['pos_x'];
        $y = (int) $curr['pos_y'];
        // 방향 처리
        $dx = 0;
        $dy = 0;
        switch ($direction) {
            case 'up':
                $dy = -1;
                break;
            case 'down':
                $dy = 1;
                break;
            case 'left':
                $dx = -1;
                break;
            case 'right':
                $dx = 1;
                break;
            default:
                http_response_code(400);
                return Response::error('invalid direction');
        }

        $nx = $x + $dx;
        $ny = $y + $dy;

        if (!DiceHelper::isValidTile($nx, $ny, $tiles)) {
            http_response_code(400);
            return Response::error('destination invalid');
        }

        // 위치 → 유저 매핑
        $posToUid = [];
        foreach ($userStates as $uid => $state) {
            $posToUid[self::posKey($state['pos_x'], $state['pos_y'])] = $uid;
        }

        // 미는게 가능한지
        if (!self::tryPush($userStates, $nx, $ny, $dx, $dy, $tiles, $posToUid, $direction)) {
            http_response_code(400);
            return Response::error('cannot push into invalid tile');
        }

        // 내 주사위 이동
        $myNewDice = DiceHelper::roll($userStates[$userId]['dice'], $direction);
        $userStates[$userId]['pos_x'] = $nx;
        $userStates[$userId]['pos_y'] = $ny;
        $userStates[$userId]['dice']  = $myNewDice;

        $goalReached = false;
        foreach ($userStates as $state) {
            $t = $tiles[$state['pos_x']][$state['pos_y']]['type'] ?? '';
            if ($t === 'goal') {
                $goalReached = true;
                break;
            }
        }

        if ($goalReached) {
            $redis->hset($roomKey, 'finished', '1');
            $redis->del("room:{$roomId}:turn_order_move");
            $redis->del("room:{$roomId}:turn_order_move");
            return Response::success(['game_end'     => $goalReached]);
        }

        $userDtos = $dao->findAllByRoomId($roomId);

        // Redis 저장
        foreach ($userStates as $uid => $state) {
            $orig = $userDtos[$uid] ?? null;
            if (!$orig) {
                continue;
            }
            $dto = new UserDto(
                $roomId,
                $uid,
                $state['pos_x'],
                $state['pos_y'],
                $orig->getExileMarkCount(),
                new DiceDto(
                    $state['dice']['top'],
                    $state['dice']['bottom'],
                    $state['dice']['left'],
                    $state['dice']['right'],
                    $state['dice']['front'],
                    $state["dice"]["back"],
                ),
                $orig->getJoinedAt()
            );
            $dao->save($dto);
            $redis->expire("room:{$roomId}:user:{$uid}", 60 * 60 * 24);
        }

        if ($started) {
            self::applyHiddenRules($roomId, $userId);
        }

        return Response::success([
            'new_position' => ['x' => $nx, 'y' => $ny],
            'dice'         => $myNewDice,
            'turn_order'   => $turnService->getTurnOrder($roomId)
        ]);
    }

    private static function applyHiddenRules(string $roomId, string $userId): bool
    {
        $redis   = getRedis();
        $userDao = new UserDao($redis);
        $roomDao = new RoomDao($redis);
        $turnSvc = new Turn();
        $rule    = new Rule();

        // 1) 룸 조회
        $roomDto = $roomDao->findByRoomId($roomId);
        if (!$roomDto) {
            return false;
        }

        // 2) DTO 일괄 생성
        $allUsers = $userDao->findAllByRoomId($roomId);

        // 3) 탈락 조건 검사 → 상태 변경 + Redis 업데이트 + 턴 추가
        foreach ($allUsers as $uid => $dto) {
            if ($dto->getPosX() < 0) {
                continue;
            }
            if ($rule->isExileCondition($roomDto, $dto)) {
                $redis->hset("room:{$roomId}:user:{$uid}", 'pos_x', -1);
                $redis->hset("room:{$roomId}:user:{$uid}", 'pos_y', -1);
                $turnSvc->advanceHiddenTurn($roomId, [
                    'user'   => $uid,
                    'action' => 'setStartTile',
                ]);

                $exileCount = $redis->hIncrBy("room:{$roomId}:user:{$uid}", 'exile_mark_count', 1);
                if ($exileCount >= 3) {
                    $turnSvc->removeUserFromTurns($roomId, $uid);
                    User::deleteUserData($uid,  $roomId);
                } else {
                    $redis->expire("room:{$roomId}:user:{$uid}", 86400);
                }
            }
        }

        // 4) 기타 룰 검사 결과를 미리 저장
        $noSameColor   = $rule->isNoSameColorInNine($roomId, $userId);
        $threeOrMore   = $rule->isThreeOrMoreInNine($roomId, $userId);
        $lineOfThree   = $rule->isLineOfThree($roomId, $userId);

        // 5) NoSameColor 턴
        if ($noSameColor) {
            $turnSvc->advanceHiddenTurn($roomId,  [
                'user'   => $userId,
                'action' => 'setDiceState',
            ]);
        }

        // 6) ThreeOrMore 턴
        if ($threeOrMore) {
            $turnSvc->advanceHiddenTurn($roomId,  [
                'user'   => $userId,
                'action' => 'targetMove'
            ]);
        }

        // 7) LineOfThree 턴
        if ($lineOfThree) {
            $turnSvc->advanceHiddenTurn($roomId,  [
                'user'   => $userId,
                'action' => 'extraTurn',
            ]);
        }

        // 8) 턴 일괄 삽입
        return true;
    }
}