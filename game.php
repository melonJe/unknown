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
    <div id="board"></div>
    <script>
        const roomId = "<?= $room_id ?>";
        // 여기에 board.js 또는 상태 관리 연결
    </script>
</body>

</html>