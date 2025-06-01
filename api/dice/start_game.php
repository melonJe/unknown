<?php

session_start();
$board = json_decode(file_get_contents(__DIR__ . '/../data/board.json'), true);

// 주사위 초기화, 위치 초기화
$_SESSION['player'] = [
    'position' => ['x' => 12, 'y' => 1], // 예시: 북쪽 시작점
    'dice' => [
        'top' => 'red',
        'bottom' => 'blue',
        'left' => 'green',
        'right' => 'yellow',
        'front' => 'purple',
        'back' => 'white'
    ]
];

echo json_encode(["status" => "ready", "board" => $board]);
