<?php

namespace DAO;

use Predis\Client;
use DTO\RoomDto;
use DTO\TileDto;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class RoomDao
{
    private Client $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @param int $roomId
     * @return RoomDto|null
     */
    public function findByRoomId(int $roomId): ?RoomDto
    {
        $key  = "room:{$roomId}";
        $data = $this->redis->hgetall($key);

        if (empty($data)) {
            return null;
        }

        // 기본값 및 타입 변환
        $state     = $data['state']   ?? '';
        $width     = isset($data['width'])  ? (int)$data['width']  : 0;
        $height    = isset($data['height']) ? (int)$data['height'] : 0;
        $tilesJson = $data['tiles']   ?? '[]';
        $createdAt = $data['created_at'] ?? null;
        $updatedAt = $data['updated_at'] ?? null;

        // tiles JSON → TileDto[]
        $tilesArray = json_decode($tilesJson, true);
        if (!is_array($tilesArray)) {
            throw new InvalidArgumentException("Invalid tiles data for room {$roomId}");
        }

        $tiles = [];
        foreach ($tilesArray as $idx => $t) {
            $tiles[] = new TileDto(
                (int)($t['x']     ?? 0),
                (int)($t['y']     ?? 0),
                (string)($t['type']  ?? ''),
                (int)($t['score'] ?? 0),
                $t['color'] ?? null
            );
        }

        // 날짜 문자열 → DateTimeImmutable
        $created = $createdAt
            ? new DateTimeImmutable($createdAt)
            : new DateTimeImmutable();
        $updated = $updatedAt
            ? new DateTimeImmutable($updatedAt)
            : new DateTimeImmutable();

        return new RoomDto(
            $roomId,
            $state,
            $width,
            $height,
            $tiles,
            $created,
            $updated
        );
    }

    /**
     * @param RoomDto $dto
     * @return void
     */
    public function save(RoomDto $dto): void
    {
        $key = "room:{$dto->getRoomId()}";

        // TileDto[] → 배열 → JSON
        $tilesForSave = array_map(
            fn(TileDto $tile) => [
                'x'     => $tile->getx(),
                'y'     => $tile->gety(),
                'type'  => $tile->getType(),
                'score' => $tile->getScore(),
                'color' => $tile->getColor(),
            ],
            $dto->getTiles()
        );
        $tilesJson = json_encode($tilesForSave);

        // 날짜 → ISO 8601 문자열
        $createdStr = $dto->getCreatedAt()->format(DateTimeInterface::ATOM);
        $updatedStr = $dto->getUpdatedAt()->format(DateTimeInterface::ATOM);

        $this->redis->hmset($key, [
            'state'      => $dto->getState(),
            'width'      => $dto->getWidth(),
            'height'     => $dto->getHeight(),
            'tiles'      => $tilesJson,
            'created_at' => $createdStr,
            'updated_at' => $updatedStr,
        ]);
    }
}
