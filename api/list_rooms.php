<?php
$room_dir = __DIR__ . '/../rooms';
$rooms = [];
$now = time();

foreach (glob($room_dir . '/room_*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    $players = $data['players'] ?? [];
    $mtime = filemtime($file);

    if (!$players && ($now - $mtime) > 300) {
        unlink($file);
    } else {
        $rooms[] = str_replace(['room_', '.json'], '', basename($file));
    }
}

echo json_encode(['rooms' => $rooms]);
