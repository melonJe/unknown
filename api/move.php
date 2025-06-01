<?php

session_start();
$data = json_decode(file_get_contents("php://input"), true);
$dir = $data['direction']; // "up", "down", etc.

$pos = $_SESSION['player']['position'];
$new = $pos;

switch ($dir) {
    case 'up':
        $new['y']--;
        break;
    case 'down':
        $new['y']++;
        break;
    case 'left':
        $new['x']--;
        break;
    case 'right':
        $new['x']++;
        break;
}

$board = json_decode(file_get_contents(__DIR__ . '/../data/board.json'), true);
$isValid = false;
foreach ($board['tiles'] as $tile) {
    if ($tile['x'] === $new['x'] && $tile['y'] === $new['y']) {
        $isValid = true;
        break;
    }
}

if ($isValid) {
    $_SESSION['player']['position'] = $new;
    echo json_encode(["status" => "moved", "position" => $new]);
} else {
    echo json_encode(["status" => "blocked"]);
}
