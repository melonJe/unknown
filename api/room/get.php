<?php

$roomId = $_GET['room_id'];
$path = __DIR__ . "/../rooms/room_$roomId.json";
if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(["error" => "room not found"]);
    exit;
}
echo file_get_contents($path);
