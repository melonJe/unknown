<?php

namespace Helpers;

class DiceHelper
{
    // 주사위 전개도 갱신
    public static function roll(array $dice, string $direction): array
    {
        // top/bottom/left/right/front/back
        switch ($direction) {
            case 'up':
                return [
                    'top' => $dice['front'],
                    'bottom' => $dice['back'],
                    'left' => $dice['left'],
                    'right' => $dice['right'],
                    'front' => $dice['bottom'],
                    'back' => $dice['top'],
                ];
            case 'down':
                return [
                    'top' => $dice['back'],
                    'bottom' => $dice['front'],
                    'left' => $dice['left'],
                    'right' => $dice['right'],
                    'front' => $dice['top'],
                    'back' => $dice['bottom'],
                ];
            case 'left':
                return [
                    'top' => $dice['top'],
                    'bottom' => $dice['bottom'],
                    'left' => $dice['front'],
                    'right' => $dice['back'],
                    'front' => $dice['right'],
                    'back' => $dice['left'],
                ];
            case 'right':
                return [
                    'top' => $dice['top'],
                    'bottom' => $dice['bottom'],
                    'left' => $dice['back'],
                    'right' => $dice['front'],
                    'front' => $dice['left'],
                    'back' => $dice['right'],
                ];
            default:
                return $dice;
        }
    }

    // 특정 위치가 유효한 타일(이동 가능)인지 검사
    public static function isValidTile(int $x, int $y, array $tiles): bool
    {
        // 좌표가 보드 범위를 벗어나면 이동 불가
        if (!isset($tiles[$x][$y])) {
            return false;
        }

        // 게임 시작 후에는 start 타일로 이동할 수 없도록 제한
        if (($tiles[$x][$y]['type'] ?? '') === 'start') {
            return false;
        }

        return true;
    }

    // 기본 주사위 전개도를 기준으로 윗면과 앞면 색으로 전체 전개도를 계산
    public static function orientationFromTopFront(string $top, string $front): ?array
    {
        static $lookup = null;
        if ($lookup === null) {
            $base = [
                'top'    => 'red',
                'bottom' => 'blue',
                'left'   => 'green',
                'right'  => 'yellow',
                'front'  => 'white',
                'back'   => 'purple',
            ];

            $lookup = [];
            $queue  = [$base];
            $seen   = [];
            while ($queue) {
                $state = array_pop($queue);
                $key = implode('|', $state);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $lookup[$state['top'] . '|' . $state['front']] = $state;
                foreach (['up', 'down', 'left', 'right'] as $dir) {
                    $queue[] = self::roll($state, $dir);
                }
            }
        }

        $k = $top . '|' . $front;
        return $lookup[$k] ?? null;
    }
}
