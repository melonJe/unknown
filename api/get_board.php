<?php

require_once __DIR__ . '/../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use App\Models\Room;

header('Content-Type: application/json');

$roomId = $_GET['room_id'] ?? null;

if (!$roomId) {
    http_response_code(400);
    echo json_encode(["error" => "room_id is required"]);
    exit;
}

$room = Room::where('room_id', $roomId)->first();

if (!$room) {
    http_response_code(404);
    echo json_encode(["error" => "room not found"]);
    exit;
}

echo $room->board;
