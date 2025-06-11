<?php

namespace Service;

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/redis.php';
require_once LIB_PATH . '/postgres.php';

use Exception;

class Rule
{
    /**
     * 1. 말 윗면이 흰색이거나 말이 흰색 칸에 있는지
     * @return bool
     */
    public function isExileCondition($board, $user): bool
    {
        $topColor = $user["dice"]["front"];
        $tileMap = [];
        // foreach ($tiles as $board["tile"]) {
        //     $key = "{$tile['x']},{$tile['y']}";
        //     $tileMap[$key] = $tile;
        // }
        $key = "{$user["pos_x"]},{$user["pos_y"]}";
        $cellColor =  isset($board[$key]) && isset($board[$key]['color'])
            ? $board[$key]['color']
            : null;  // 없으면 null 반환
        return $topColor === 'white' || $cellColor === 'white';
    }

    /**
     * 2. 추방 징표가 3개인지
     * @return bool
     */
    public function isThreeExileMarks($player): bool
    {
        return $player->getExileMarkCount() >= 3;
    }

    /**
     * 4. 주변 9칸에 같은 색 윗면 말이 없을 때
     * @return bool
     */
    public function isNoSameColorInNine($board, $dice): bool
    {
        $neighbors = $board->getNeighbors($dice->getPosition(), 1);
        $topColor  = $dice->getTopColor();
        foreach ($neighbors as $pos) {
            $other = $board->getdiceAt($pos);
            if ($other && $other->getTopColor() === $topColor) {
                return false;
            }
        }
        return true;
    }

    /**
     * 5. 주변 9칸에 같은 색 윗면 말이 3개 이상일 때
     * @return bool
     */
    public function isThreeOrMoreInNine($board, $dice): bool
    {
        $neighbors = $board->getNeighbors($dice->getPosition(), 1);
        $topColor  = $dice->getTopColor();
        $count     = 0;
        foreach ($neighbors as $pos) {
            $other = $board->getdiceAt($pos);
            if ($other && $other->getTopColor() === $topColor) {
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
     * @return bool
     */
    public function isYellowSpecial($board, $dice): bool
    {
        if ($dice->getTopColor() !== 'yellow') {
            return false;
        }
        $neighbors = $board->getNeighbors($dice->getPosition(), 1);
        $count     = 0;
        foreach ($neighbors as $pos) {
            if ($board->getdiceAt($pos)) {
                $count++;
                if ($count >= 3) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 7. 일직선(수평/수직/대각)으로 3칸 같은 색 윗면인지
     * @return bool
     */
    public function isLineOfThree($board, $dice): bool
    {
        $directions = [[1, 0], [0, 1], [1, 1], [1, -1]];
        $topColor   = $dice->getTopColor();
        $pos        = $dice->getPosition();
        foreach ($directions as $dir) {
            if ($board->isLineOfSameColor($pos, $dir, 3, $topColor)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 3. 밀어내기 연쇄 방지: 현재 푸시 이벤트인지 검사
     * (예시: 이벤트 타입 플래그 확인)
     * @return bool
     */
    public function isPushChainPrevented(string $eventType): bool
    {
        return $eventType === 'push';
    }

    /**
     * 8. 그 외 후속 조건 예시 (필요 시 추가)
     * @return bool
     */
    public function customCondition(): bool
    {
        // 추가 조건 로직 작성
        return false;
    }
}
