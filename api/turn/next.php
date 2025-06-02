<?php

use Models\RoomTurn;
use Models\RoomUser;

$roomId = $_POST['room_id'] ?? null;

$turn = RoomTurn::where('room_id', $roomId)->first();
$users = RoomUser::where('room_id', $roomId)->get()->pluck('user_id')->values()->all();

$currentIndex = array_search($turn->current_turn_user_id, $users);
$nextIndex = ($currentIndex + 1) % count($users);

$turn->update([
    'current_turn_user_id' => $users[$nextIndex]
]);

echo json_encode(['next' => $users[$nextIndex]]);
