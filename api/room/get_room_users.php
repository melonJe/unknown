<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use App\Models\RoomUser;

$roomId = $_GET['room_id'] ?? null;

if (!$roomId) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id is required']);
    exit;
}

$users = RoomUser::where('room_id', $roomId)
    ->get(['user_id', 'pos_x', 'pos_y', 'dice']);

echo json_encode(['users' => $users]);
