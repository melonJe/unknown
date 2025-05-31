<?php
$roomId = substr(md5(uniqid()), 0, 6);
$defaultBoard = file_get_contents(__DIR__ . '/../data/board.json');
file_put_contents(__DIR__ . "/../rooms/room_$roomId.json", json_encode([
    "room_id" => $roomId,
    "players" => [],
    "turn" => 0,
    "board" => json_decode($defaultBoard, true)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(["room_id" => $roomId]);
