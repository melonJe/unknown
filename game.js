(() => {
  const body = document.body;
  const roomId = body.dataset.roomId;
  const websocketUrl = body.dataset.websocketUrl;

  let myUserId;
  let boardWidth;
  let boardData;
  let ws;
  let selectedTile = null;

  // start dice faces (color names)
  let startDiceData = {
    top: 'red',
    bottom: 'blue',
    left: 'green',
    right: 'yellow',
    front: 'white',
    back: 'purple'
  };

  const knownColors = ['red', 'blue', 'yellow', 'green', 'purple', 'white'];

  function setFaceColors(cube, diceData) {
    const faces = ['top', 'bottom', 'left', 'right', 'front', 'back'];
    faces.forEach(face => {
      const el = cube.querySelector(`.face.${face}`);
      if (!el) return;
      // remove previous color classes
      knownColors.forEach(c => el.classList.remove(c));
      // add current color name
      const color = diceData[face];
      if (color) el.classList.add(color);
    });
  }

  async function getUserId() {
    const res = await fetch('/api/user/me.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'include'
    });
    const data = await res.json();
    return data.user_id;
  }

  function getBoardWidth() {
    return boardWidth || 0;
  }

  function displayTurnOrder(order) {
    const el = document.getElementById('turn-order');
    const result = order.map(({ user, action }) => `${user}(${action})`);
    el.textContent = result && result.length ? 'Turn: ' + result.join(' → ') : '';
  }

  function rollDice(data, dir) {
    switch (dir) {
      case 'up':
        return { top: data.front, bottom: data.back, left: data.left, right: data.right, front: data.bottom, back: data.top };
      case 'down':
        return { top: data.back, bottom: data.front, left: data.left, right: data.right, front: data.top, back: data.bottom };
      case 'left':
        return { top: data.top, bottom: data.bottom, left: data.front, right: data.back, front: data.right, back: data.left };
      case 'right':
        return { top: data.top, bottom: data.bottom, left: data.back, right: data.front, front: data.left, back: data.right };
      default:
        return data;
    }
  }

  function setupDiceDrag(cube, onEnd) {
    let dragging = false;
    let startX = 0, startY = 0;
    let rotX = 0, rotY = 0;
    let dragStart = null;

    cube.addEventListener('mousedown', (e) => {
      dragging = true;
      startX = e.clientX; startY = e.clientY;
      dragStart = { x: e.clientX, y: e.clientY };
      cube.style.transition = 'none';
    });

    document.addEventListener('mousemove', (e) => {
      if (!dragging) return;
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      startX = e.clientX; startY = e.clientY;
      rotY += dx * 0.5;
      rotX -= dy * 0.5;
      rotX = Math.max(-45, Math.min(45, rotX));
      rotY = Math.max(-45, Math.min(45, rotY));
      cube.style.transform = `rotateX(${rotX}deg) rotateY(${rotY}deg)`;
    });

    document.addEventListener('mouseup', (e) => {
      if (!dragging) return;
      dragging = false;
      cube.style.transition = 'transform 0.5s ease';
      cube.style.transform = 'rotateX(0deg) rotateY(0deg)';
      const dx = e.clientX - dragStart.x;
      const dy = e.clientY - dragStart.y;
      dragStart = null;
      rotX = 0; rotY = 0;
      onEnd(dx, dy);
    });
  }

  function renderBoard(data) {
    if (data.started === '1') {
      document.getElementById('startBtn').style.display = 'none';
    }
    const board = document.getElementById('board');
    boardWidth = data.width;
    document.documentElement.style.setProperty('--cols', data.width);
    board.style.display = 'grid';
    board.style.gridTemplateColumns = `repeat(${data.width}, var(--tile-size))`;
    board.style.gridTemplateRows = `repeat(${data.height}, var(--tile-size))`;
    board.innerHTML = '';

    const tileMap = Array.from({ length: data.height }, () => Array.from({ length: data.width }, () => null));
    for (const tile of data.tiles) {
      tileMap[tile.y - 1][tile.x - 1] = tile;
    }

    for (let y = 0; y < data.height; y++) {
      for (let x = 0; x < data.width; x++) {
        const tile = tileMap[y][x];
        const div = document.createElement('div');
        div.className = 'tile';
        div.dataset.x = x + 1;
        div.dataset.y = y + 1;
        if (tile) {
          div.classList.add(tile.type);
          if (tile.type === 'floor' && tile.color) div.classList.add(tile.color);
          if ('score' in tile) div.textContent = tile.score;
        } else {
          div.classList.add('empty');
        }
        board.appendChild(div);
      }
    }
  }

  function createDiceElement(diceData, tileData, isMine = false, userId = null) {
    tileData.textContent = '';
    const container = document.createElement('div');
    container.className = 'dice-container';
    if (userId) container.dataset.userId = userId;
    if (diceData && diceData.front) container.dataset.front = diceData.front;

    const cube = document.createElement('div');
    cube.className = 'dice';

    ['top', 'bottom', 'left', 'right', 'front', 'back'].forEach(face => {
      const f = document.createElement('div');
      f.className = `face ${face} ${diceData[face] || 'white'}`;
      cube.appendChild(f);
    });

    container.appendChild(cube);

    setupDiceDrag(cube, (dx, dy) => {
      if (!isMine) return;
      if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
      const direction = Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up');
      ws.send(JSON.stringify({ action: 'move', room_id: roomId, user_id: myUserId, direction }));
    });

    return container;
  }

  function renderUsers(users) {
    document.querySelectorAll('.dice-container').forEach(el => el.remove());
    for (const [user_id, userData] of Object.entries(users)) {
      const { dice, pos_x, pos_y } = userData;
      if (pos_x > 0 && pos_y > 0) {
        const tileIndex = (pos_y - 1) * getBoardWidth() + (pos_x - 1);
        const tile = document.getElementById('board').children[tileIndex];
        const isMine = (user_id === myUserId);
        const diceEl = createDiceElement(dice, tile, isMine, user_id);
        tile.appendChild(diceEl);
      }
    }
  }

  function enableStartSelection() {
    document.getElementById('start-dice').innerHTML = '';
    document.querySelectorAll('.tile.start').forEach(t => {
      t.classList.add('selectable');
      t.addEventListener('click', handleStartClick);
    });
  }

  function disableStartSelection() {
    document.querySelectorAll('.tile.start').forEach(t => {
      t.classList.remove('selectable');
      t.replaceWith(t.cloneNode(true));
    });
    document.getElementById('start-dice').innerHTML = '';
    if (selectedTile) {
      selectedTile.innerHTML = selectedTile.textContent.trim();
      selectedTile = null;
    }
  }

  function handleStartClick(e) {
    const tile = e.currentTarget;
    if (selectedTile && selectedTile !== tile) {
      selectedTile.innerHTML = selectedTile.textContent.trim();
    }
    selectedTile = tile;
    tile.textContent = '';

    const container = document.createElement('div');
    container.className = 'dice-container';

    let cube = document.createElement('div');
    cube.className = 'dice';
    ['top', 'bottom', 'left', 'right', 'front', 'back'].forEach(face => {
      const faceDiv = document.createElement('div');
      faceDiv.className = `face ${face} ${startDiceData[face] || 'white'}`;
      cube.appendChild(faceDiv);
    });
    container.appendChild(cube);

    cube.addEventListener('dblclick', () => {
      ws.send(JSON.stringify({
        action: 'set_start',
        room_id: roomId,
        user_id: myUserId,
        x: Number(tile.dataset.x),
        y: Number(tile.dataset.y),
        dice: startDiceData
      }));
      disableStartSelection();
    });

    setupDiceDrag(cube, (dx, dy) => {
      if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
      const dir = Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up');
      startDiceData = rollDice(startDiceData, dir);
      setFaceColors(cube, startDiceData);
    });

    tile.appendChild(container);
  }

  function setDiceState(user) {
    const tileIndex = (user.pos_y - 1) * getBoardWidth() + (user.pos_x - 1);
    const tile = document.getElementById('board').children[tileIndex];
    const cube = tile.querySelector('.dice');
    const newCube = cube.cloneNode(true);
    cube.parentNode.replaceChild(newCube, cube);

    newCube.addEventListener('dblclick', () => {
      ws.send(JSON.stringify({
        action: 'set_dice_state',
        room_id: roomId,
        user_id: myUserId,
        x: Number(tile.dataset.x),
        y: Number(tile.dataset.y),
        dice: startDiceData
      }));
      disableStartSelection();
    });

    setupDiceDrag(newCube, (dx, dy) => {
      if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
      const dir = Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up');
      startDiceData = rollDice(startDiceData, dir);
      setFaceColors(newCube, startDiceData);
    });
  }

  function targetMove(user) {
    const board = document.getElementById('board');
    const allContainers = board.querySelectorAll('.dice-container');
    if (!allContainers || allContainers.length === 0) {
      console.warn('targetMove: dice element not found');
      return;
    }

    allContainers.forEach(container => {
      const targetUserId = container.dataset.userId;
      if (!targetUserId || targetUserId === myUserId) return; // exclude mine

      const targetFront = container.dataset.front; // color name
      const myFront = user?.dice?.front;
      if (myFront !== 'yellow' && myFront !== targetFront) return;

      const cube = container.querySelector('.dice');
      if (!cube) return;

      const tile = container.parentNode;
      const newCube = cube.cloneNode(true);
      cube.parentNode.replaceChild(newCube, cube);
      tile.classList.add('selectable');

      setupDiceDrag(newCube, (dx, dy) => {
        if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
        const direction = Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up');
        ws.send(JSON.stringify({ action: 'target_move', room_id: roomId, user_id: myUserId, target_user_id: targetUserId, direction }));
        newCube.replaceWith(newCube.cloneNode(true));
        tile.classList.remove('selectable');
      });
    });
  }

  function handleAction(action) {
    switch (action) {
      case 'goBack':
        history.back();
        break;
      case 'goHome':
        window.location.href = '/';
        break;
      default:
        console.warn('Unknown action:', action);
    }
  }

  // Init
  window.addEventListener('DOMContentLoaded', async () => {
    // back button wiring
    const backBtn = document.getElementById('backBtn');
    if (backBtn) backBtn.addEventListener('click', () => { window.location.href = 'index.php'; });

    try {
      myUserId = await getUserId();
      ws = new WebSocket(`${websocketUrl}/?roomId=${encodeURIComponent(roomId)}&userId=${encodeURIComponent(myUserId)}`);

      ws.onopen = () => {
        ws.send(JSON.stringify({ action: 'join_room', room_id: roomId, user_id: myUserId }));
        const startBtn = document.getElementById('startBtn');
        if (startBtn) {
          startBtn.addEventListener('click', () => {
            ws.send(JSON.stringify({ action: 'start_game', room_id: roomId, user_id: myUserId }));
          });
        }
      };

      ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        switch (msg.type) {
          case 'board_data':
            boardData = msg.board;
            break;
          case 'user_out':
            renderBoard(boardData);
            break;
          case 'dices_data':
            if (msg.board) boardData = msg.board;
            renderBoard(boardData);
            renderUsers(msg.dices.player);
            break;
          case 'next_turn':
            displayTurnOrder(msg.turn_order);
            const currentUser = msg.turn_order[0];
            if (currentUser.user === myUserId && currentUser.action === 'setStartTile') enableStartSelection();
            if (currentUser.user === myUserId && currentUser.action === 'setDiceState') setDiceState(msg.user);
            if (currentUser.user === myUserId && currentUser.action === 'targetMove') targetMove(msg.user);
            break;
          case 'dice_moved':
            renderUsers(msg.user ? { [msg.user.id]: msg.user } : {}); // fallback minimal update
            break;
          case 'error':
            alert(msg.message);
            if (msg.action) handleAction(msg.action);
            break;
          default:
            console.warn('Unknown message:', msg);
        }
      };

      ws.onclose = () => {};
      ws.onerror = () => { try { ws.close(); } catch (e) {} };
    } catch (e) {
      console.error('Failed to init user/ws:', e);
    }
  });
})();
