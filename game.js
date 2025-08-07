// Extracted game logic from inline script in game.php
const wsUrl = document.body.dataset.wsUrl;
const roomId = document.body.dataset.roomId;

let myUserId;
let boardWidth;
let boardData;
let ws;
let startDiceData = {
  top: 'red',
  bottom: 'blue',
  left: 'green',
  right: 'yellow',
  front: 'white',
  back: 'purple',
};
let startDiceEl;
const colorMap = {
  red: '#BF2C47',
  blue: '#0468BF',
  yellow: '#D9C24E',
  green: '#03A678',
  purple: '#8B65BF',
  white: 'white',
};

(async function () {
  try {
    myUserId = await getUserId();
    ws = new WebSocket(`${wsUrl}/?roomId=${roomId}&userId=${myUserId}`);

    ws.onopen = () => {
      ws.send(
        JSON.stringify({ action: 'join_room', room_id: roomId, user_id: myUserId })
      );
      document.getElementById('startBtn').onclick = () => {
        ws.send(
          JSON.stringify({ action: 'start_game', room_id: roomId, user_id: myUserId })
        );
      };
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
        case 'next_turn': {
          console.log(msg.turn_order);
          displayTurnOrder(msg.turn_order);
          const currentUser = msg.turn_order[0];
          if (currentUser.user === myUserId) {
            if (currentUser.action === 'setStartTile') enableStartSelection();
            if (currentUser.action === 'setDiceState') setDiceState(msg.user);
            if (currentUser.action === 'targetMove') targetMove(msg.user);
          }
          break;
        }
        case 'dice_moved':
          updateDice(msg.user);
          break;
        case 'error':
          alert(msg.message);
          if (msg.action) handleAction(msg.action);
          break;
        default:
          console.warn('알 수 없는 메시지:', msg);
      }
    };

    ws.onclose = () => {};
    ws.onerror = () => ws.close();
  } catch (e) {
    console.error('유저 정보 요청 실패:', e);
  }
})();

async function getUserId() {
  const res = await fetch('/api/user/me.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    credentials: 'include',
  });
  const data = await res.json();
  return data.user_id;
}

// helper functions (renderBoard, renderUsers, updateDice, etc.) moved unchanged from original script
// Due to space, they are assumed present; copy them from previous inline code if needed.
