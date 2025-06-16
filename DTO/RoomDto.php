<?php

namespace DTO;

use DateTimeInterface;
use InvalidArgumentException;

class RoomDto
{
    private int $roomId;
    private string $state;
    private int $width;
    private int $height;
    /**
     * @var TileDto[]
     */
    private array $tiles;
    private DateTimeInterface $created_at;
    private DateTimeInterface $updated_at;

    /**
     * @param TileDto[]           $tiles
     * @param DateTimeInterface   $created_at
     * @param DateTimeInterface   $updated_at
     */
    public function __construct(
        int $roomId,
        string $state,
        int $width,
        int $height,
        array $tiles,
        DateTimeInterface $created_at,
        DateTimeInterface $updated_at
    ) {
        $this->roomId     = $roomId;
        $this->state      = $state;
        $this->width      = $width;
        $this->height     = $height;
        $this->validateTiles($tiles);
        $this->tiles          = $tiles;
        $this->created_at     = $created_at;
        $this->updated_at     = $updated_at;
    }

    public function getRoomId(): int
    {
        return $this->roomId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return TileDto[]
     */
    public function getTiles(): array
    {
        return $this->tiles;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updated_at;
    }

    /**
     * 배열 요소가 모두 TileDto 인스턴스인지 검사
     *
     * @param array $tiles
     * @throws InvalidArgumentException
     */
    private function validateTiles(array $tiles): void
    {
        foreach ($tiles as $idx => $tile) {
            if (!$tile instanceof TileDto) {
                $type = is_object($tile) ? get_class($tile) : gettype($tile);
                throw new InvalidArgumentException(
                    "tiles[{$idx}] must be instance of TileDto, {$type} given."
                );
            }
        }
    }
}
