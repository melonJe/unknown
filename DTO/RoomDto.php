<?php

namespace DTO;

use DateTimeInterface;
use InvalidArgumentException;

class RoomDto
{
    private string $roomId;
    private bool $started;
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
        string $roomId,
        bool $started,
        int $width,
        int $height,
        array $tiles,
        DateTimeInterface $created_at,
        DateTimeInterface $updated_at
    ) {
        $this->roomId     = $roomId;
        $this->started    = $started;
        $this->width      = $width;
        $this->height     = $height;
        $this->validateTiles($tiles);
        $this->tiles          = $tiles;
        $this->created_at     = $created_at;
        $this->updated_at     = $updated_at;
    }

    public function getRoomId(): string
    {
        return $this->roomId;
    }

    public function isStarted(): bool
    {
        return $this->started;
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
    public function getNeighbors(array $position, int $radius): array
    {
        [$x, $y]      = $position;
        $neighbors    = [];

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dy = -$radius; $dy <= $radius; $dy++) {
                // 자기 자신 제외
                if ($dx === 0 && $dy === 0) {
                    continue;
                }

                $nx = $x + $dx;
                $ny = $y + $dy;

                // 보드 경계 검사
                if ($nx < 0 || $ny < 0 || $nx >= $this->width || $ny >= $this->height) {
                    continue;
                }
                $neighbors[] = [$nx, $ny];
            }
        }

        return $neighbors;
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
    public function toArray(): array
    {
        return [
            'room_id'    => $this->roomId,
            'started'    => $this->started,
            'width'      => $this->width,
            'height'     => $this->height,
            'tiles'      => array_map(fn(TileDto $t) => $t->toArray(), $this->tiles),
            'created_at' => $this->created_at->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updated_at->format(DateTimeInterface::ATOM),
        ];
    }
}
