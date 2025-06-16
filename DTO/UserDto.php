<?php

namespace DTO;

use DateTimeImmutable;
use DateTimeInterface;

class UserDto
{
    private string $roomId;
    private string $userId;
    private DateTimeInterface $joinedAt;
    private int $posX;
    private int $posY;
    private DiceDto $dice;

    public function __construct(
        string $roomId,
        string $userId,
        DateTimeInterface $joinedAt,
        int $posX,
        int $posY,
        DiceDto $dice
    ) {
        $this->roomId   = $roomId;
        $this->userId   = $userId;
        $this->joinedAt = $joinedAt;
        $this->posX     = $posX;
        $this->posY     = $posY;
        $this->dice     = $dice;
    }

    public function getRoomId(): string
    {
        return $this->roomId;
    }
    public function getUserId(): string
    {
        return $this->userId;
    }
    public function getJoinedAt(): DateTimeInterface
    {
        return $this->joinedAt;
    }
    public function getPosX(): int
    {
        return $this->posX;
    }
    public function getPosY(): int
    {
        return $this->posY;
    }
    public function getDice(): DiceDto
    {
        return $this->dice;
    }

    /**
     * Redis에서 hgetall로 가져온 데이터 배열로부터 UserDto 생성
     *
     * @param array<string,string> $data
     * @return self
     */
    public static function fromRedis(array $data): self
    {
        return new self(
            $data['room']       ?? '',
            $data['user']       ?? '',
            new DateTimeImmutable($data['joined_at'] ?? 'now'),
            (int)($data['pos_x'] ?? 0),
            (int)($data['pos_y'] ?? 0),
            DiceDto::fromJson($data['dice'] ?? '{}')
        );
    }

    /**
     * Redis에 저장할 배열로 변환
     *
     * @return array<string,string>
     */
    public function toRedis(): array
    {
        return [
            'room'       => $this->roomId,
            'user'       => $this->userId,
            'joined_at'  => $this->joinedAt->format(DateTimeInterface::ATOM),
            'pos_x'      => (string)$this->posX,
            'pos_y'      => (string)$this->posY,
            'dice'       => json_encode($this->dice->toArray()),
        ];
    }
}
