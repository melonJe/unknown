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

    public function findByRoomId(string $roomId): ?RoomDto
    {
        $key  = "room:{$roomId}";
        $data = $this->redis->hgetall($key);
        if (empty($data)) {
            return null;
        }

        // 기본값 및 타입 변환
        $started   = isset($data['started']) && $data['started'] !== '0';
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
        foreach ($tilesArray as $x_coord_str => $tile_datas) {
            foreach ($tile_datas as $y_coord_str => $tile_data) {
                $scoreValue = $tile_data['score'] ?? 0;
                $scoreForDto = ($scoreValue === 'G') ? 10000 : (int)$scoreValue;
                $tiles[] = new TileDto(
                    (int)$x_coord_str, // X 좌표를 정수로 변환
                    (int)$y_coord_str, // Y 좌표를 정수로 변환
                    (string)($tile_data['type']  ?? ''), // 'type' 필드
                    $scoreForDto,                         // 'score' 필드
                    $tile_data['color'] ?? null           // 'color' 필드
                );
            }
        }
        $created = $createdAt
            ? new DateTimeImmutable($createdAt)
            : new DateTimeImmutable();
        $updated = $updatedAt
            ? new DateTimeImmutable($updatedAt)
            : new DateTimeImmutable();

        return new RoomDto(
            $roomId,
            $started,
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
                'x'     => $tile->getX(),
                'y'     => $tile->getY(),
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
            'started'    => $dto->isStarted() ? '1' : '0',
            'width'      => $dto->getWidth(),
            'height'     => $dto->getHeight(),
            'tiles'      => $tilesJson,
            'created_at' => $createdStr,
            'updated_at' => $updatedStr,
        ]);
    }

    public function findPositionsByRoom(string $roomId): array
    {
        $setKey    = "room:{$roomId}:users";
        $userIds   = $this->redis->smembers($setKey);
        $positions = [];

        foreach ($userIds as $userId) {
            $hashKey = "room:{$roomId}:user:{$userId}";
            $data    = $this->redis->hmget($hashKey, ['pos_x', 'pos_y']);
            [$posX, $posY] = $data;

            $positions[$userId] = [
                isset($posX) ? (int)$posX : 0,
                isset($posY) ? (int)$posY : 0,
            ];
        }

        return $positions;
    }

    public function getTilesWithDiceColor(string $roomId, array $users): array
    {
        $room = $this->findByRoomId($roomId);
        if (!$room) {
            return [];
        }

        // 1) TileDto[] → [x][y] 그리드
        $grid = [];
        foreach ($room->getTiles() as $tile) {
            $x = $tile->getX();
            $y = $tile->getY();
            $grid[$x][$y] = [
                'type'  => $tile->getType(),
                'score' => $tile->getScore(),
                'color' => $tile->getColor(),
            ];
        }

        // 2) 각 유저 위치 덮어쓰기
        foreach ($users as $user) {
            $x     = $user->getPosX();
            $y     = $user->getPosY();
            $front = $user->getDice()->getFrontColor();
            if (isset($grid[$x][$y])) {
                $grid[$x][$y]['color'] = $front;
            }
        }

        return $grid;
    }
}