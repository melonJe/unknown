<?php

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

<body>
    <h1>방 ID: <?= htmlspecialchars($room_id) ?></h1>
    <button onclick="location.href='index.php'">🔙 대기실로</button>
    <div id="board">
    </div>

    <script>
        const roomId = "<?= $room_id ?>";
        fetch("/api/room/join.php", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'include',
            body: new URLSearchParams({
                room_id: roomId
            })
        });

        fetch(`/api/get_board.php?room_id=${roomId}`)
            .then(res => res.json())
            .then(data => renderBoard(data));

    </script>

    <script src="game.js"></script>
</body>

</html>