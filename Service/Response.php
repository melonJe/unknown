<?php

namespace Service;

class Response
{
    /**
     * Build a success response with optional additional data.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function success(array $data = []): array
    {
        return array_merge(['success' => true], $data);
    }

    /**
     * Build an error response with a message.
     *
     * @param string $message
     * @return array<string,mixed>
     */
    public static function error(string $message): array
    {
        return [
            'success' => false,
            'error'   => $message,
        ];
    }
}
