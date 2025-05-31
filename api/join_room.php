<?php
session_start();
$roomId = $_POST['room_id'];
$nickname = $_POST['nickname'] ?? 'guest';
$path = __DIR__ . "/../rooms/room_$roomId.json";

if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(["error" => "room not found"]);
    exit;
}

$room = json_decode(file_get_contents($path), true);
$room['players'][] = [
    "name" => $nickname,
    "position" => ["x" => 12, "y" => 1],
    "dice" => []
];
file_put_contents($path, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$_SESSION['room_id'] = $roomId;
$_SESSION['nickname'] = $nickname;

echo json_encode(["status" => "joined", "room" => $room]);
