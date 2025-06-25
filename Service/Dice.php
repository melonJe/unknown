<?php

namespace Service;

use Helpers\DiceHelper;
use DAO\UserDao;
use DAO\RoomDao;
use DTO\UserDto;
use DTO\DiceDto;
use Service\Turn;

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

    /**
     * Check every user's current position and front color for exile conditions.
     * Users meeting the condition will receive an exile mark and a turn will be
     * queued to let them select a new start tile.
     *
     * @param string                $roomId
     * @param array<string,UserDto> $allUsers
     * @param array<string,array>   $userStates
     * @param Rule                  $rule
     * @param RoomDto               $roomDto
     * @param UserDao               $userDao
     * @param Turn                  $turnService
     * @param string                $currentUser
     * @param array<int,string>     $events
     * @return void
     */
    private static function applyExileMarks(
        string $roomId,
        array $allUsers,
        array &$userStates,
        Rule $rule,
        RoomDto $roomDto,
        UserDao $userDao,
        Turn $turnService,
        string $currentUser,
        array &$events
    ): void {
        foreach ($allUsers as $uid => $dto) {
            if ($dto->getPosX() === -1 || $dto->getPosY() === -1) {
                continue;
            }

            if ($rule->isExileCondition($roomDto, $dto, $userDao)) {
                $userStates[$uid]['pos_x'] = -1;
                $userStates[$uid]['pos_y'] = -1;
                $turnService->insertTurn($roomId, [
                    'user'   => $uid,
                    'action' => 'setStartTile',
                ]);

                if ($uid === $currentUser) {
                    $events[] = 'exile';
                }
            }
        }
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


        //턴 체크 및 게임 시작 여부 확인
        $turnService = new Turn();
        $turnUser  = $turnService->getCurrentTurn($roomId)["user"];
        $started  = isset($roomData['started']) && $roomData['started'] !== '0';
        if ($started && $turnUser !== $userId) {
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

        // 히든 룰 적용
        $extra     = self::applyHiddenRules($roomId, $userId, $userStates, $userDtos, $tiles);
        $userDtos  = $dao->findAllByRoomId($roomId);

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

        return [
            'success'      => true,
            'new_position' => ['x' => $nx, 'y' => $ny],
            'dice'         => $myNewDice,
            'extra_turn'   => in_array('extra_turn', $extra, true),
            'exile'        => in_array('exile', $extra, true),
        ];
    }

    /**
     * Apply hidden rules after movement and modify user states accordingly.
     *
     * @param string                   $roomId
     * @param string                   $userId
     * @param array<string,array>      $userStates
     * @param array<string,UserDto>    $userDtos
     * @param array                    $tiles
     * @return array<int,string>       List of triggered events
     */
    private static function applyHiddenRules(string $roomId, string $userId, array &$userStates, array $userDtos, array $tiles): array
    {
        $redis   = getRedis();
        $userDao = new UserDao($redis);
        $roomDao = new \DAO\RoomDao($redis);
        $roomDto = $roomDao->findByRoomId((int)$roomId);
        if (!$roomDto) {
            return [];
        }

        $rule = new \Rule();
        $allUsers = [];
        foreach ($userStates as $uid => $state) {
            $orig = $userDtos[$uid];
            $allUsers[$uid] = new UserDto(
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
                    $state['dice']['back'],
                ),
                $orig->getJoinedAt()
            );
        }

        $events      = [];
        $turnService = new Turn();

        // First, apply exile checks for every user on the board.
        self::applyExileMarks(
            $roomId,
            $allUsers,
            $userStates,
            $rule,
            $roomDto,
            $userDao,
            $turnService,
            $userId,
            $events
        );

        if (in_array('exile', $events, true)) {
            return $events;
        }

        $user = $allUsers[$userId];

        if ($rule->isNoSameColorInNine($roomDto, $user, $allUsers, $roomDao)) {
            $colors = ['red', 'blue', 'yellow', 'green', 'purple', 'white'];
            $current = $userStates[$userId]['dice']['top'];
            do {
                $new = $colors[array_rand($colors)];
            } while ($new === $current);
            $userStates[$userId]['dice']['top'] = $new;
        }

        if ($rule->isThreeOrMoreInNine($roomDto, $user, $allUsers, $roomDao)) {
            $neighbors = $roomDto->getNeighbors([$user->getPosX(), $user->getPosY()], 1);
            $neighborMap = [];
            foreach ($neighbors as [$nx, $ny]) {
                $neighborMap["{$nx},{$ny}"] = true;
            }

            foreach ($allUsers as $uid => $dto) {
                if ($uid === $userId) {
                    continue;
                }
                if ($dto->getDice()->getFrontColor() !== $user->getDice()->getFrontColor()) {
                    continue;
                }

                $posKey = $dto->getPosX() . ',' . $dto->getPosY();
                if (!isset($neighborMap[$posKey])) {
                    continue;
                }

                $sx = $dto->getPosX();
                $sy = $dto->getPosY();
                if (DiceHelper::isValidTile($sx, $sy - 1, $tiles)) {
                    $userStates[$uid]['pos_y'] = $sy - 1;
                }
                break;
            }
        }

        if ($rule->isYellowSpecial($roomDto, $user, $allUsers, $roomDao)) {
            foreach ($allUsers as $uid => $dto) {
                if ($uid === $userId) {
                    continue;
                }
                $sx = $dto->getPosX();
                $sy = $dto->getPosY();
                if (DiceHelper::isValidTile($sx, $sy - 1, $tiles)) {
                    $userStates[$uid]['pos_y'] = $sy - 1;
                }
                break;
            }
        }

        if ($rule->isLineOfThree($roomDto, $user, $allUsers, $roomDao)) {
            $events[] = 'extra_turn';
        }

        return $events;
    }
}
