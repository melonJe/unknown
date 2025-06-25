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

    /**
     * Get tile X coordinate.
     */
    public function getX(): int
    {
        return $this->x;
    }
    /**
     * Get tile Y coordinate.
     */
    public function getY(): int
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
    public function toArray(): array
    {
        return [
            'x'     => $this->x,
            'y'     => $this->y,
            'type'  => $this->type,
            'score' => $this->score,
            'color' => $this->color,
        ];
    }

}
