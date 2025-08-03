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
    // 사용자 정보 로드 (기존 register_user.php 사용)
    let myUserId;
    let ws;
    fetch('/api/user/register_user.php')
      .then(res => res.json())
      .then(user => {
        myUserId = user.user_id;
        ws = new WebSocket("ws://unknown_websocket.meloncaput.com:8080/?&userId=" + myUserId);
        ws.onopen = () => {
          console.log('웹소켓 연결됨');
          ws.send(JSON.stringify({
            action: "get_room_list"
          }));
        };

        ws.onmessage = (event) => {
          const msg = JSON.parse(event.data);
          console.log(msg.type)
          // 서버에서 방 목록 정보 직접 push
          switch (msg.type) {
            case 'room_list':
              console.log(msg.rooms)
              renderRoomList(msg.rooms);
              break

            case 'room_created':
              location.href = `game.php?room_id=${msg.room_id}`;
              break

            case 'room_list_changed':
              ws.send(JSON.stringify({
                action: "get_room_list"
              }));
              break
          };
        };

        ws.onclose = () => {
          console.log('웹소켓 연결 끊김');
        };

        ws.onerror = (e) => {
          console.log('웹소켓 오류:', e);
          ws.close();
        };
      });


    function renderRoomList(rooms) {
      const list = document.getElementById('room-list');
      list.textContent = '';

      if (!rooms || !Object.keys(rooms).length) {
        list.innerHTML = '<li>현재 참여 가능한 방이 없습니다.</li>';
        return;
      }

      const frag = document.createDocumentFragment();

      Object
        .entries(rooms)
        .sort(([, a], [, b]) => (a === '1') - (b === '1'))
        .forEach(([id, started]) => {
          const li = document.createElement('li');
          if (started !== '1') {
            const a = document.createElement('a');
            a.href = `game.php?room_id=${encodeURIComponent(id)}`;
            a.textContent = `▶ 방 #${id}`;
            li.appendChild(a);
          } else {
            li.textContent = `▶ 방 #${id}`;
            li.classList.add('disabled-room');
          }
          frag.appendChild(li);
        });

      list.appendChild(frag);
    }


    async function createRoom() {
      ws.send(JSON.stringify({
        action: "create_room",
        user_id: myUserId
      }));
    }
  </script>
</body>

</html>