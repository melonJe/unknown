<?php

namespace DTO;

use DateTimeImmutable;
use DateTimeInterface;

class UserDto
{
    private string $roomId;
    private string $userId;
    private int $posX;
    private int $posY;
    private int $exileMarkCount;
    private DiceDto $dice;
    private DateTimeInterface $joinedAt;

    public function __construct(
        string $roomId,
        string $userId,
        int $posX,
        int $posY,
        int $exileMarkCount,
        DiceDto $dice,
        DateTimeInterface $joinedAt,
    ) {
        $this->roomId   = $roomId;
        $this->userId   = $userId;
        $this->joinedAt = $joinedAt;
        $this->posX     = $posX;
        $this->posY     = $posY;
        $this->exileMarkCount     = $exileMarkCount;
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
    public function setDice(DiceDto $dice): void
    {
        $this->dice = $dice;
    }
    public function getExileMarkCount(): int
    {
        return $this->exileMarkCount;
    }
    public function setExileMarkCount(int $count): void
    {
        $this->exileMarkCount = $count;
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
            (int)($data['pos_x'] ?? 0),
            (int)($data['pos_y'] ?? 0),
            (int)($data['exile_mark_count'] ?? 0),
            DiceDto::fromJson($data['dice'] ?? '{}'),
            new DateTimeImmutable($data['joined_at'] ?? 'now'),
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
            'pos_x'      => (string)$this->posX,
            'pos_y'      => (string)$this->posY,
            'exile_mark_count'      => (string)$this->exileMarkCount,
            'dice'       => json_encode($this->dice->toArray()),
            'joined_at'  => $this->joinedAt->format(DateTimeInterface::ATOM),
        ];
    }
    /**
     * Convert DTO to plain array for application use.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'room'             => $this->roomId,
            'user'             => $this->userId,
            'pos_x'            => $this->posX,
            'pos_y'            => $this->posY,
            'exile_mark_count' => $this->exileMarkCount,
            'dice'             => $this->dice->toArray(),
            'joined_at'        => $this->joinedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
