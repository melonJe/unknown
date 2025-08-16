(() => {
  const body = document.body;
  const websocketUrl = body.dataset.websocketUrl;

  let myUserId;
  let ws;

  function isWsOpen() {
    return ws && ws.readyState === WebSocket.OPEN;
  }

  function renderRoomList(rooms) {
    const list = document.getElementById('room-list');
    list.textContent = '';

    if (!rooms || !Object.keys(rooms).length) {
      const li = document.createElement('li');
      li.textContent = '현재 참여 가능한 방이 없습니다.';
      list.appendChild(li);
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

  function bindCreateRoom() {
    const createBtn = document.getElementById('create-room-btn');
    if (!createBtn) return;
    createBtn.addEventListener('click', async () => {
      if (!isWsOpen()) {
        console.warn('WebSocket not ready; cannot create room yet.');
        return;
      }
      ws.send(JSON.stringify({ action: 'create_room', user_id: myUserId }));
    });
  }

  async function init() {
    const createBtn = document.getElementById('create-room-btn');
    if (createBtn) createBtn.disabled = true;

    try {
      const res = await fetch('/api/user/register_user.php');
      const text = await res.text();
      const user = JSON.parse(text);
      myUserId = user.user_id;

      ws = new WebSocket(`${websocketUrl}/?&userId=${encodeURIComponent(myUserId)}`);

      ws.onopen = () => {
        if (createBtn) createBtn.disabled = false;
        ws.send(JSON.stringify({ action: 'get_room_list' }));
      };

      ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        switch (msg.type) {
          case 'room_list':
            renderRoomList(msg.rooms);
            break;
          case 'room_created':
            window.location.href = `game.php?room_id=${msg.room_id}`;
            break;
          case 'room_list_changed':
            ws.send(JSON.stringify({ action: 'get_room_list' }));
            break;
          default:
            // ignore
            break;
        }
      };

      ws.onclose = () => {
        if (createBtn) createBtn.disabled = true;
      };

      ws.onerror = () => {
        if (createBtn) createBtn.disabled = true;
        try { ws.close(); } catch (_) {}
      };

      bindCreateRoom();
    } catch (err) {
      console.error('초기화 실패 (사용자/웹소켓):', err);
      if (createBtn) createBtn.disabled = true;
    }
  }

  window.addEventListener('DOMContentLoaded', init);
})();
