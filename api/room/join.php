<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use App\Models\Room;
use App\Models\RoomUser;

session_start();


try {
    $roomId = $_POST['room_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$roomId || !$user_id) {
        http_response_code(400);
        echo json_encode(["error" => "room_id and user_id are required"]);
        exit;
    }

    $room = Room::where('room_id', $roomId)->first();

    if (!$room) {
        http_response_code(404);
        echo json_encode(["error" => "room not found"]);
        exit;
    }

    $roomuser = RoomUser::create([
        'room_id' => $room->room_id,
        'user_id' => $user_id,
        'pos_x' => 1,
        'pos_y' => 1,
        'dice' => [
            'top' => 'red',
            'bottom' => 'blue',
            'left' => 'green',
            'right' => 'yellow',
            'front' => 'white',
            'back' => 'purple',
        ]
    ]);

    $_SESSION['room_id'] = $roomId;

    echo json_encode([
        "status" => "joined",
        "room" => $room,
        "player" => $roomuser
    ]);
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "DB error",
        "message" => $e->getMessage()
    ]);
}
