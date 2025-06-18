<?php

namespace Service;

use Helpers\DiceHelper;
use DAO\UserDao;
use DTO\UserDto;
use DTO\DiceDto;

class Dice
{
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
            return ["error" => "room_id, direction, user_id required"];
        }

        $redis    = getRedis();
        $dao      = new UserDao($redis);
        $redis->expire("user:{$userId}", 60 * 60 * 24);
        $roomKey = "room:{$roomId}";
        $redis->expire($roomKey, 60 * 60 * 24);
        $roomData = $redis->hgetall($roomKey);


        //턴 체크
        $turnUser = $redis->hget("room:{$roomId}:turn", "current_turn_user_id");
        if ($roomData["state"] || $turnUser !== $userId) {
            http_response_code(403);
            return ['error' => 'not your turn'];
        }

        if (!$roomData) {
            http_response_code(404);
            return ["error" => "room not found"];
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
                return ['error' => 'invalid direction'];
        }

        $nx = $x + $dx;
        $ny = $y + $dy;

        if (!DiceHelper::isValidTile($nx, $ny, $tiles)) {
            http_response_code(400);
            return ['error' => 'destination invalid'];
        }

        // 위치 → 유저 매핑
        $posToUid = [];
        foreach ($userStates as $uid => $state) {
            $posToUid[self::posKey($state['pos_x'], $state['pos_y'])] = $uid;
        }

        // 미는게 가능한지
        if (!self::tryPush($userStates, $nx, $ny, $dx, $dy, $tiles, $posToUid, $direction)) {
            http_response_code(400);
            return ['error' => 'cannot push into invalid tile'];
        }

        // 내 주사위 이동
        $myNewDice = DiceHelper::roll($userStates[$userId]['dice'], $direction);
        $userStates[$userId]['pos_x'] = $nx;
        $userStates[$userId]['pos_y'] = $ny;
        $userStates[$userId]['dice']  = $myNewDice;

        // 히든 룰 체크 (확장 포인트)
        // if (HiddenRuleHelper::shouldFire(...)) { ... }

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

        // 턴 넘기기 (확장용, 주석)
        $nextUser = null;
        // $allTurnList = $redis->lrange("room:{$roomId}:turn_order", 0, -1);
        // $curIdx = array_search($userId, $allTurnList);
        // $nextIdx = ($curIdx + 1) % count($allTurnList);
        // $nextUser = $allTurnList[$nextIdx];
        // $redis->hset("room:{$roomId}:turn", "current_turn_user_id", $nextUser);

        // 반환
        return [
            'success'      => true,
            'new_position' => ['x' => $nx, 'y' => $ny],
            'dice'         => $myNewDice,
            'next_turn'    => $nextUser,
        ];
    }
}
