<?php

namespace Service;

class Board
{
    public static function getStartTiles(string $tilesJson): array
    {
        $tiles = json_decode($tilesJson, true) ?: [];

        $startTiles = [];
        foreach ($tiles as $x => $row) {
            foreach ($row as $y => $tile) {
                if (($tile['type'] ?? '') === 'start') {
                    $startTiles[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        return $startTiles;
    }
}
