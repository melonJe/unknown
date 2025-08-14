<?php

$websocket_url = getenv('WEBSOCKET_URL') ?: 'wss://unknown_ws.meloncaput.com';
$room_id = $_GET['room_id'] ?? '';
if (!$room_id) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>방 #<?= htmlspecialchars($room_id) ?></title>
    <link rel="stylesheet" href="style.css">
</head>

<body data-ws-url="<?= $websocket_url ?>" data-room-id="<?= $room_id ?>">
    <h1>방 ID: <?= htmlspecialchars($room_id) ?></h1>
    <button id="backButton">🔙 대기실로</button>
    <button id="startBtn">Start Game</button>
    <div id="board"></div>
    <div id="turn-order"></div>
    <div id="start-dice"></div>
  <script src="game.js"></script>
</body>

</html>