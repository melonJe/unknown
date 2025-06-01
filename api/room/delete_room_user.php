<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use App\Models\RoomUser;

try {
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;

    RoomUser::where('user_id', $user_id)
        ->delete();
} catch (Exception $e) {
    file_put_contents(BASE_PATH . '/debug.log', "[ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        "error" => "DB error",
        "message" => $e->getMessage()
    ]);
}
