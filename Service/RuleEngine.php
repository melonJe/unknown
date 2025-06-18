<?php

namespace Service;

use DTO\RoomDto;
use DTO\UserDto;
use DAO\RoomDao;

class RuleEngine
{
    /**
     * Validate move against all rules.
     */
    public function validateMove(RoomDto $room, UserDto $user, string $direction, array $allUsers): bool
    {
        if (!$this->noSameColorInNine($room, $user, $allUsers)) {
            return false;
        }
        if ($this->threeOrMoreInNine($room, $user, $allUsers)) {
            return false;
        }
        if ($this->yellowSpecial($room, $user, $allUsers)) {
            return false;
        }
        if ($this->lineOfThree($room, $user, $allUsers)) {
            return false;
        }
        if ($this->hiddenEight($room, $user, $allUsers)) {
            return false;
        }
        return true;
    }

    /** Hidden rule #4 */
    public function noSameColorInNine(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        $grid = $room->getTiles();
        foreach ($allUsers as $u) {
            $grid[$u->getPosX()][$u->getPosY()]['color'] = $u->getDice()->getFrontColor();
        }
        $neighbors = $room->getNeighbors([$user->getPosX(), $user->getPosY()], 1);
        $color = $user->getDice()->getFrontColor();
        foreach ($neighbors as [$x, $y]) {
            if (($grid[$x][$y]['color'] ?? null) === $color) {
                return false;
            }
        }
        return true;
    }

    /** Hidden rule #5 */
    public function threeOrMoreInNine(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        $grid = $room->getTiles();
        foreach ($allUsers as $u) {
            $grid[$u->getPosX()][$u->getPosY()]['color'] = $u->getDice()->getFrontColor();
        }
        $neighbors = $room->getNeighbors([$user->getPosX(), $user->getPosY()], 1);
        $color = $user->getDice()->getFrontColor();
        $count = 0;
        foreach ($neighbors as [$x, $y]) {
            if (($grid[$x][$y]['color'] ?? null) === $color) {
                if (++$count >= 3) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Hidden rule #6 */
    public function yellowSpecial(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        if ($user->getDice()->getFrontColor() !== 'yellow') {
            return false;
        }
        $grid = $room->getTiles();
        foreach ($allUsers as $u) {
            $grid[$u->getPosX()][$u->getPosY()]['color'] = $u->getDice()->getFrontColor();
        }
        $neighbors = $room->getNeighbors([$user->getPosX(), $user->getPosY()], 1);
        $count = 0;
        foreach ($neighbors as [$x, $y]) {
            if (isset($grid[$x][$y]['color'])) {
                if (++$count >= 3) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Hidden rule #7 */
    public function lineOfThree(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        $grid = $room->getTiles();
        foreach ($allUsers as $u) {
            $grid[$u->getPosX()][$u->getPosY()]['color'] = $u->getDice()->getFrontColor();
        }
        $startX = $user->getPosX();
        $startY = $user->getPosY();
        $color  = $user->getDice()->getFrontColor();
        $directions = [[1,0],[0,1],[1,1],[1,-1]];
        foreach ($directions as [$dx,$dy]) {
            $count = 1;
            for ($i=1;$i<3;$i++) {
                $nx = $startX + $dx*$i;
                $ny = $startY + $dy*$i;
                if (($grid[$nx][$ny]['color'] ?? null) === $color) {
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

    /** Hidden rule #8 - placeholder always false */
    public function hiddenEight(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        return false;
    }
}

