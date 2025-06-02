<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use Models\Room;
use Models\RoomUser;
use Models\RoomTurn;
use Helpers\DiceHelper;

session_start();

$roomId = $_POST['room_id'] ?? null;
$direction = $_POST['direction'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

$turn = RoomTurn::where('room_id', $roomId)->first();
if ($turn->current_turn_user_id !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'not your turn']);
    exit;
}

if (!$roomId || !$userId) {
    http_response_code(400);
    echo json_encode(["error" => "room_id or user_id missing"]);
    exit;
}

$room = Room::where('room_id', $roomId)->firstOrFail();
$boardTiles = collect(json_decode($room->board, true)['tiles']);

// $roomUsers = RoomUser::where('room_id', $roomId)->get()->keyBy(fn($u) => "{$u->pos_x},{$u->pos_y}")->all();

$currentUser = RoomUser::where('room_id', $roomId)->where('user_id', $userId)->firstOrFail();
file_put_contents('php://stdout', print_r($userId, true));
if (!$currentUser) {
    http_response_code(404);
    echo json_encode(['error' => 'user not on board']);
    exit;
}

$x = $currentUser->pos_x;
$y = $currentUser->pos_y;

$dx = 0;
$dy = 0;
switch ($direction) {
    case 'up':
        $dy = -1;
        break;
    case 'down':
        $dy = 1;
        break;
    case 'left':
        $dx = -1;
        break;
    case 'right':
        $dx = 1;
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'invalid direction']);
        exit;
}

$nx = $x + $dx;
$ny = $y + $dy;

// if (!DiceHelper::isValidTile($nx, $ny, $boardTiles)) {
//     http_response_code(400);
//     echo json_encode(['error' => 'destination invalid']);
//     exit;
// }

// if (!DiceHelper::tryPush($nx, $ny, $dx, $dy, $boardTiles, $roomUsers)) {
//     http_response_code(400);
//     echo json_encode(['error' => 'cannot push into invalid tile']);
//     exit;
// }

// unset($roomUsers["{$currentUser->pos_x},{$currentUser->pos_y}"]);

$currentUser = DiceHelper::move(clone $currentUser, $dx, $dy);
$currentUser->save();

echo json_encode([
    'success' => true,
    'new_position' => ['x' => $nx, 'y' => $ny],
    'dice' => $currentUser->dice
]);
