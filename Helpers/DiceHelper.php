<?php

namespace Helpers;

use Illuminate\Support\Collection;
use Models\RoomUser;

class DiceHelper
{
    public static function move($user, $dx, $dy): RoomUser
    {
        $x = $user->pos_x;
        $y = $user->pos_y;

        $nx = $x + $dx;
        $ny = $y + $dy;

        $dice = $user->dice;
        if ($dx !== 0) {
            $dice = match ($dx) {
                -1 => [
                    'top' => $dice['top'],
                    'bottom' => $dice['bottom'],
                    'left' => $dice['front'],
                    'right' => $dice['back'],
                    'front' => $dice['right'],
                    'back' => $dice['left'],
                ],
                1 => [
                    'top' => $dice['top'],
                    'bottom' => $dice['bottom'],
                    'left' => $dice['back'],
                    'right' => $dice['front'],
                    'front' => $dice['left'],
                    'back' => $dice['right'],
                ],
            };
        } elseif ($dy !== 0) {
            $dice = match ($dy) {
                -1 => [
                    'top' => $dice['front'],
                    'bottom' => $dice['back'],
                    'left' => $dice['left'],
                    'right' => $dice['right'],
                    'front' => $dice['bottom'],
                    'back' => $dice['top'],
                ],
                1 => [
                    'top' => $dice['back'],
                    'bottom' => $dice['front'],
                    'left' => $dice['left'],
                    'right' => $dice['right'],
                    'front' => $dice['top'],
                    'back' => $dice['bottom'],
                ],
            };
        }


        $user->pos_x = $nx;
        $user->pos_y = $ny;
        $user->dice = $dice;

        return $user;
    }


    public static function isValidTile(int $x, int $y, Collection $tiles): bool
    {
        return (bool) $tiles->firstWhere(
            fn($t) =>
            is_array($t) &&
            isset($t['x'], $t['y'], $t['type']) &&
            $t['x'] === $x &&
            $t['y'] === $y &&
            $t['type'] !== 'start'
        );
    }

    /**
     * @param array<string, RoomUser> $users
     */
    public static function tryPush(int $x, int $y, int $dx, int $dy, Collection $tiles, array &$users): bool
    {
        $targetKey = "{$x},{$y}";

        if (!isset($users[$targetKey])) {
            return true;
        }

        $nextX = $x + $dx;
        $nextY = $y + $dy;
        $nextKey = "{$nextX},{$nextY}";

        if (!self::isValidTile($nextX, $nextY, $tiles)) {
            return false;
        }

        if (!self::tryPush($nextX, $nextY, $dx, $dy, $tiles, $users)) {
            return false;
        }

        $pushedUser = $users[$targetKey];
        unset($users[$targetKey]);
        $users[$nextKey] = self::move($pushedUser, $dx, $dy);

        return true;
    }
}
