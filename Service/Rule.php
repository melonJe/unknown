<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';


use DAO\UserDao;
use DTO\RoomDto;
use DTO\UserDto;
use DAO\RoomDao;

class Rule
{
    /**
     * 1. 말 윗면이 흰색이거나 말이 흰색 칸에 있는지
     *
     * @param RoomDto $room
     * @param UserDto $user
     * @return bool
     */
    public function isExileCondition(RoomDto $roomDto, UserDto $userDto): bool
    {
        $frontColor = $userDto->getDice()->getFrontColor();
        $tileColor = null;
        foreach ($roomDto->getTiles() as $tile) {
            if ($tile->getX() === $userDto->getPosX() && $tile->getY() === $userDto->getPosY()) {
                $tileColor = $tile->getColor();
                break;
            }
        }

        $isExile = $frontColor === 'white' || $tileColor === 'white';

        return $isExile;
    }

    /**
     * 2. 추방 징표가 3개인지
     *
     * @param UserDto $user
     * @return bool
     */
    public function isThreeExileMarks(UserDto $user): bool
    {
        return $user->getExileMarkCount() >= 3;
    }

    public function isNoSameColorInNine($roomId, $userId): bool
    {
        $roomDao = new RoomDao(getRedis());
        $userDao = new UserDao(getRedis());
        $roomDto = $roomDao->findByRoomId($roomId);
        $grid = $roomDao->getTilesWithDiceColor(
            $roomDto->getRoomId(),
            $userDao->findAllByRoomId($roomId)
        );
        $user = $userDao->findByRoomAndUserId($roomId, $userId);
        if ($user->getPosX() <= 0 && $user->getPosY() <= 0) {
            return false;
        }
        $center    = [$user->getPosX(), $user->getPosY()];
        $neighbors = $roomDto->getNeighbors($center, 1);
        $frontColor  = $user->getDice()->getFrontColor();
        foreach ($neighbors as [$x, $y]) {
            if (($grid[$x][$y]['color'] ?? null) === $frontColor) {
                return false;
            }
        }
        return true;
    }

    /**
     * 5. 주변 9칸(자신 포함)에 같은 색 윗면 말이 3개 이상일 때
     *
     * @param RoomDto     $room
     * @param UserDto     $user
     * @param UserDto[]   $allUsers
     * @param RoomDao     $roomDao
     * @return bool
     */
    public function isThreeOrMoreInNine($roomId, $userId): bool
    {
        $roomDao = new RoomDao(getRedis());
        $userDao = new UserDao(getRedis());
        $roomDto = $roomDao->findByRoomId($roomId);
        $allUsers = $userDao->findAllByRoomId($roomId);
        $userDto = $userDao->findByRoomAndUserId($roomId, $userId);
        if ($userDto->getPosX() <= 0 && $userDto->getPosY() <= 0) {
            return false;
        }
        $grid      = $roomDao->getTilesWithDiceColor($roomDto->getRoomId(), $allUsers);
        $center    = [$userDto->getPosX(), $userDto->getPosY()];
        $neighbors = $roomDto->getNeighbors($center, 1);
        $frontColor  = $userDto->getDice()->getFrontColor();
        $count     = 1; // 본인 포함

        foreach ($neighbors as [$x, $y]) {
            if (($grid[$x][$y]['color'] ?? null) === $frontColor) {
                $count++;
                if ($count >= 3) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 6. 노란색 윗면일 때 주변 9칸에 말이 3개 이상 있는지
     *
     * @param RoomDto     $room
     * @param UserDto     $user
     * @param UserDto[]   $allUsers
     * @param RoomDao     $roomDao
     * @return bool
     */
    public function isYellowSpecial($roomId, $userId): bool
    {
        $roomDao = new RoomDao(getRedis());
        $userDao = new UserDao(getRedis());
        $roomDto = $roomDao->findByRoomId($roomId);
        $userDto = $userDao->findByRoomAndUserId($roomId, $userId);
        if ($userDto->getPosX() <= 0 && $userDto->getPosY() <= 0) {
            return false;
        }
        $allUsers = $userDao->findAllByRoomId($roomId);
        $frontColor = $userDto->getDice()->getFrontColor();
        if ($frontColor !== 'yellow') {
            return false;
        }

        $grid      = $roomDao->getTilesWithDiceColor($roomDto->getRoomId(), $allUsers);
        $center    = [$userDto->getPosX(), $userDto->getPosY()];
        $neighbors = $roomDto->getNeighbors($center, 1);
        $count     = 0;

        foreach ($neighbors as [$x, $y]) {
            // 칸에 주사위가 있는지만 검사
            if (isset($grid[$x][$y]['color'])) {
                if (++$count >= 3) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 7. 일직선(수평/수직/대각)으로 3칸 같은 색 윗면인지
     *
     * @param RoomDto     $room
     * @param UserDto     $user
     * @param UserDto[]   $allUsers
     * @param RoomDao     $roomDao
     * @return bool
     */
    public function isLineOfThree($roomId, $userId): bool
    {
        $roomDao = new RoomDao(getRedis());
        $userDao = new UserDao(getRedis());
        $roomDto = $roomDao->findByRoomId($roomId);
        $userDto = $userDao->findByRoomAndUserId($roomId, $userId);
        if ($userDto->getPosX() <= 0 && $userDto->getPosY() <= 0) {
            return false;
        }
        $allUsers = $userDao->findAllByRoomId($roomId);
        $grid       = $roomDao->getTilesWithDiceColor($roomDto->getRoomId(), $allUsers);
        $startX     = $userDto->getPosX();
        $startY     = $userDto->getPosY();
        $frontColor   = $userDto->getDice()->getFrontColor();
        $directions = [[1, 0], [0, 1], [1, 1], [1, -1]];

        foreach ($directions as [$dx, $dy]) {
            $count = 1; // 자기 자신 포함
            for ($i = 1; $i < 3; $i++) {
                $nx = $startX + $dx * $i;
                $ny = $startY + $dy * $i;
                if (($grid[$nx][$ny]['color'] ?? null) === $frontColor) {
                    $count++;
                } else {
                    break;
                }
            }
            if ($count >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * 3. 밀어내기 연쇄 방지: 현재 푸시 이벤트인지 검사
     *
     * @param string $eventType
     * @return bool
     */
    public function isPushChainPrevented(string $eventType): bool
    {
        return $eventType === 'push';
    }
}