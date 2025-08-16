<?php

$websocket_url = getenv('WEBSOCKET_URL');
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <title>보드게임 대기실</title>
  <link rel="stylesheet" href="style.css">
</head>

<body data-websocket-url="<?= htmlspecialchars($websocket_url) ?>">
  <h1>보드게임 대기실</h1>

  <button id="create-room-btn">➕ 방 만들기</button>

  <h2>진행 중인 방</h2>
  <ul id="room-list">
    <li>불러오는 중...</li>
  </ul>

  <script src="index.js" defer></script>
</body>

</html>