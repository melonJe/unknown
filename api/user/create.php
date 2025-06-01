<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

$defaultBoard = file_get_contents(DATA_PATH . '/board.json');

use App\Models\Room;

try {
    // 고유한 방 ID 생성 (6자리 해시)
    $roomId = substr(md5(uniqid()), 0, 10);

    // 기본 보드 데이터 로드 (JSON string)
    $defaultBoard = json_decode(file_get_contents(DATA_PATH . '/board.json'), true); // 배열로 읽기

    $room = Room::create([
        'room_id' => $roomId,
        'board' => $defaultBoard // 🔵 json_encode() 하지 마세요
    ]);

    echo json_encode(["room_id" => $room->room_id]);
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "DB error",
        "message" => $e->getMessage()
    ]);
}
