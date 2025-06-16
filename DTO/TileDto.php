<?php

namespace DTO;

class TileDto
{
    private int $x;
    private int $y;
    private string $type;
    private int    $score;
    private ?string $color;

    public function __construct(int $x, int $y, string $type, int $score, ?string $color)
    {
        $this->x   = $x;
        $this->y   = $y;
        $this->type   = $type;
        $this->score  = $score;
        $this->color  = $color;
    }

    public function getx(): int
    {
        return $this->x;
    }
    public function gety(): int
    {
        return $this->y;
    }
    public function getType(): string
    {
        return $this->type;
    }
    public function getScore(): int
    {
        return $this->score;
    }
    public function getColor(): ?string
    {
        return $this->color;
    }
}
