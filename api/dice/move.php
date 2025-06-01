<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use App\Models\RoomUser;

session_start();

$roomId = $_POST['room_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$direction = $_POST['direction'] ?? null;

if (!$roomId || !$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "room_id {$roomId} and user_id {$user_id} are required"]);
    exit;
}

$roomUser = RoomUser::where('room_id', $roomId)
    ->where('user_id', $user_id)
    ->firstOrFail();

$dice = $roomUser->dice ?? null; // 기본 주사위 상태

if (!$dice) {
    http_response_code(400);
    echo json_encode(["error" => "not found dice"]);
    exit;
}
$x = $roomUser->pos_x;
$y = $roomUser->pos_y;

switch ($direction) {
    case 'up':
        $y -= 1;
        $dice = [
            'top' => $dice['front'],
            'bottom' => $dice['back'],
            'left' => $dice['left'],
            'right' => $dice['right'],
            'front' => $dice['bottom'],
            'back' => $dice['top'],
        ];
        break;

    case 'down':
        $y += 1;
        $dice = [
            'top' => $dice['back'],
            'bottom' => $dice['front'],
            'left' => $dice['left'],
            'right' => $dice['right'],
            'front' => $dice['top'],
            'back' => $dice['bottom'],
        ];
        break;

    case 'left':
        $x -= 1;
        $dice = [
            'top' => $dice['top'],
            'bottom' => $dice['bottom'],
            'left' => $dice['front'],
            'right' => $dice['back'],
            'front' => $dice['right'],
            'back' => $dice['left'],
        ];
        break;

    case 'right':
        $x += 1;
        $dice = [
            'top' => $dice['top'],
            'bottom' => $dice['bottom'],
            'left' => $dice['back'],
            'right' => $dice['front'],
            'front' => $dice['left'],
            'back' => $dice['right'],
        ];
        break;
}

$roomUser->update([
    'pos_x' => $x,
    'pos_y' => $y,
    'dice' => $dice
]);

echo json_encode([
    'success' => true,
    'new_position' => ['x' => $x, 'y' => $y],
    'dice' => $dice
]);
