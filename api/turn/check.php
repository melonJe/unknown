<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use Models\RoomTurn;

session_start();

$myUserId = $_SESSION['user_id'] ?? null;

$roomId = $_GET['room_id'] ?? null;
if (!$roomId) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id is required']);
    exit;
}

$turn = RoomTurn::where('room_id', $roomId)->first();
if (!$turn) {
    http_response_code(404);
    echo json_encode(['error' => 'Turn info not found']);
    exit;
}

return $turn->current_turn_user_id === $myUserId;
