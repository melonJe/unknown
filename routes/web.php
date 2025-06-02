<?php
use Illuminate\Support\Str;
use Models\Room;

Route::post('/create-room', function () {
    $roomId = substr(md5(uniqid()), 0, 6);
    $board = file_get_contents(base_path('data/board.json'));

    $room = Room::create([
        'room_id' => $roomId,
        'board' => $board
    ]);

    return response()->json(['room_id' => $room->room_id]);
});