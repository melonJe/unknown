<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <title>보드게임 대기실</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      font-family: sans-serif;
      padding: 20px;
    }

    #room-list {
      list-style: none;
      padding: 0;
    }

    #room-list li {
      margin: 8px 0;
    }
  </style>
</head>

<body>
  <h1>보드게임 대기실</h1>

  <button onclick="createRoom()">➕ 방 만들기</button>

  <h2>진행 중인 방</h2>
  <ul id="room-list">
    <li>불러오는 중...</li>
  </ul>

  <script>
    fetch(`/api/room/delete_room_user.php`, {
      method: 'DELETE',
      credentials: 'include',
    });

    fetch('/api/user/register_user.php')
      .then(res => res.json())
      .then(user => {
        console.log('내 정보:', user);
      });

    fetch('/api/user/list.php')
      .then(res => res.json())
      .then(({
        users
      }) => {
        console.log('전체 유저:', users);
      });

    async function createRoom() {
      const res = await fetch('/api/room/create.php', {
        method: 'POST'
      });
      const data = await res.json();
      location.href = `game.php?room_id=${data.room_id}`;
    }

    async function loadRooms() {
      const res = await fetch('/api/room/list.php');
      const data = await res.json();
      const list = document.getElementById('room-list');
      list.innerHTML = '';

      if (data.rooms.length === 0) {
        list.innerHTML = '<li>현재 참여 가능한 방이 없습니다.</li>';
        return;
      }

      data.rooms.forEach(roomId => {
        const li = document.createElement('li');
        li.innerHTML = `<a href="game.php?room_id=${roomId}">▶ 방 #${roomId}</a>`;
        list.appendChild(li);
      });
    }

    loadRooms();
  </script>
</body>

</html>