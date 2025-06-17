<?php

namespace DTO;

use DateTimeImmutable;
use DateTimeInterface;

class DiceDto
{
    private string $top;
    private string $bottom;
    private string $left;
    private string $right;
    private string $front;
    private string $back;

    public function __construct(
        string $top,
        string $bottom,
        string $left,
        string $right,
        string $front,
        string $back
    ) {
        $this->top    = $top;
        $this->bottom = $bottom;
        $this->left   = $left;
        $this->right  = $right;
        $this->front  = $front;
        $this->back   = $back;
    }

    public function getTopColor(): string
    {
        return $this->top;
    }
    public function getBottomColor(): string
    {
        return $this->bottom;
    }
    public function getLeftColor(): string
    {
        return $this->left;
    }
    public function getRightColor(): string
    {
        return $this->right;
    }
    public function getFrontColor(): string
    {
        return $this->front;
    }
    public function getBackColor(): string
    {
        return $this->back;
    }

    /**
     * JSON 문자열로부터 DiceDto 생성
     *
     * @param string $json
     * @return self
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        return new self(
            $data['top']    ?? '',
            $data['bottom'] ?? '',
            $data['left']   ?? '',
            $data['right']  ?? '',
            $data['front']  ?? '',
            $data['back']   ?? ''
        );
    }

    /**
     * 객체를 연관 배열로 변환
     *
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'top'    => $this->top,
            'bottom' => $this->bottom,
            'left'   => $this->left,
            'right'  => $this->right,
            'front'  => $this->front,
            'back'   => $this->back,
        ];
    }
}
