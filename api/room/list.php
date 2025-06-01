<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Support\Carbon;

$now = Carbon::now();

$activeRoomIds = RoomUser::distinct()->pluck('room_id')->toArray();

Room::whereNotIn('room_id', $activeRoomIds)->delete();

$rooms = Room::pluck('room_id');

echo json_encode(['rooms' => $rooms]);
