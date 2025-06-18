<?php

namespace Service;

use DTO\RoomDto;
use DTO\UserDto;

class RuleEngine
{
    /**
     * Build grid merged with users' dice colors.
     *
     * @param RoomDto        $room
     * @param UserDto[]      $users
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function buildColorGrid(RoomDto $room, array $users): array
    {
        $grid = [];
        foreach ($room->getTiles() as $tile) {
            $grid[$tile->getx()][$tile->gety()] = [
                'type'  => $tile->getType(),
                'score' => $tile->getScore(),
                'color' => $tile->getColor(),
            ];
        }
        foreach ($users as $u) {
            $grid[$u->getPosX()][$u->getPosY()]['color'] = $u->getDice()->getFrontColor();
        }
        return $grid;
    }
    /**
     * Validate move against all rules.
     *
     * @param RoomDto   $room
     * @param UserDto   $user
     * @param string    $direction
     * @param UserDto[] $allUsers
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

    /**
     * Hidden rule #4.
     * Around the player, no tile may have the same color.
     */
    public function noSameColorInNine(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        $grid = $this->buildColorGrid($room, $allUsers);
        $neighbors = $room->getNeighbors([$user->getPosX(), $user->getPosY()], 1);
        $color = $user->getDice()->getFrontColor();
        foreach ($neighbors as [$x, $y]) {
            if (($grid[$x][$y]['color'] ?? null) === $color) {
                return false;
            }
        }
        return true;
    }

    /**
     * Hidden rule #5.
     * Three or more matching colors around the player fail the move.
     */
    public function threeOrMoreInNine(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        $grid = $this->buildColorGrid($room, $allUsers);
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

    /**
     * Hidden rule #6.
     * Yellow dice face requires at least three neighbors to block.
     */
    public function yellowSpecial(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        if ($user->getDice()->getFrontColor() !== 'yellow') {
            return false;
        }
        $grid = $this->buildColorGrid($room, $allUsers);
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

    /**
     * Hidden rule #7.
     * Creates a line of three with same color.
     */
    public function lineOfThree(RoomDto $room, UserDto $user, array $allUsers): bool
    {
        $grid = $this->buildColorGrid($room, $allUsers);
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

