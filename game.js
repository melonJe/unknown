// Extracted game logic from inline script in game.php
const wsUrl = document.body.dataset.wsUrl;
const roomId = document.body.dataset.roomId;

document.getElementById("backButton").addEventListener("click", function () {
  location.href = "index.php";
});

let myUserId;
let boardWidth;
let boardData;
let ws;
let startDiceData = {
  top: "red",
  bottom: "blue",
  left: "green",
  right: "yellow",
  front: "white",
  back: "purple",
};
let startDiceEl;
const colorMap = {
  red: "#BF2C47",
  blue: "#0468BF",
  yellow: "#D9C24E",
  green: "#03A678",
  purple: "#8B65BF",
  white: "white",
};

(async function () {
  try {
    myUserId = await getUserId();
    ws = new WebSocket(`${wsUrl}?roomId=${roomId}&userId=${myUserId}`);

    ws.onopen = () => {
      ws.send(
        JSON.stringify({
          action: "join_room",
          room_id: roomId,
          user_id: myUserId,
        })
      );
      document.getElementById("startBtn").onclick = () => {
        ws.send(
          JSON.stringify({
            action: "start_game",
            room_id: roomId,
            user_id: myUserId,
          })
        );
      };
    };

    ws.onmessage = (event) => {
      const msg = JSON.parse(event.data);
      switch (msg.type) {
        case "board_data":
          boardData = msg.board;
          break;
        case "user_out":
          renderBoard(boardData);
          break;
        case "dices_data":
          if (msg.board) boardData = msg.board;
          renderBoard(boardData);
          renderUsers(msg.dices.player);
          break;
        case "next_turn": {
          console.log(msg.turn_order);
          displayTurnOrder(msg.turn_order);
          const currentUser = msg.turn_order[0];
          if (currentUser.user === myUserId) {
            if (currentUser.action === "setStartTile") enableStartSelection();
            if (currentUser.action === "setDiceState") setDiceState(msg.user);
            if (currentUser.action === "targetMove") targetMove(msg.user);
          }
          break;
        }
        case "dice_moved":
          updateDice(msg.user);
          break;
        case "error":
          alert(msg.message);
          if (msg.action) handleAction(msg.action);
          break;
        default:
          console.warn("알 수 없는 메시지:", msg);
      }
    };

    ws.onclose = () => {};
    ws.onerror = () => ws.close();
  } catch (e) {
    console.error("유저 정보 요청 실패:", e);
  }
})();

async function getUserId() {
  const res = await fetch("/api/user/me.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    credentials: "include",
  });
  const data = await res.json();
  return data.user_id;
}
async function getUserId() {
  const res = await fetch("/api/user/me.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    credentials: "include",
  });

  // 응답을 JSON으로 파싱
  const data = await res.json();
  return data.user_id;
}

// 보드 렌더 함수 (기존 renderBoard 함수와 동일)
function renderBoard(data) {
  if (data.started === "1") {
    document.getElementById("startBtn").style.display = "none";
  }
  const board = document.getElementById("board");
  boardWidth = data.width;
  document.documentElement.style.setProperty("--cols", data.width);
  board.style.display = "grid";
  board.style.gridTemplateColumns = `repeat(${data.width}, var(--tile-size))`;
  board.style.gridTemplateRows = `repeat(${data.height}, var(--tile-size))`;
  board.innerHTML = "";

  // 2차원 배열로 타일 정보 매핑
  const tileMap = Array.from(
    {
      length: data.height,
    },
    () =>
      Array.from(
        {
          length: data.width,
        },
        () => null
      )
  );
  for (const tile of data.tiles) {
    tileMap[tile.y - 1][tile.x - 1] = tile;
  }

  for (let y = 0; y < data.height; y++) {
    for (let x = 0; x < data.width; x++) {
      const tile = tileMap[y][x];
      const div = document.createElement("div");
      div.className = "tile";

      // x, y 좌표 dataset에 저장
      div.dataset.x = x + 1; // 1부터 시작하고 싶으면 +1
      div.dataset.y = y + 1;

      if (tile) {
        div.classList.add(tile.type);
        if (tile.type === "floor" && tile.color) {
          div.classList.add(tile.color);
        }
        if ("score" in tile) {
          div.textContent = tile.score;
        }
      } else {
        div.style.backgroundColor = "#eee";
      }

      board.appendChild(div);
    }
  }
}

function renderUsers(users) {
  document.querySelectorAll(".dice-container").forEach((el) => el.remove());
  for (const [user_id, userData] of Object.entries(users)) {
    const { dice, pos_x, pos_y } = userData;
    if (pos_x > 0 && pos_y > 0) {
      const tileIndex = (pos_y - 1) * getBoardWidth() + (pos_x - 1);
      const tile = document.getElementById("board").children[tileIndex];
      const isMine = user_id === myUserId;
      const diceEl = createDiceElement(dice, tile, isMine);
      tile.appendChild(diceEl);
    }
  }
}

// 단일 사용자 주사위 정보 업데이트 (move 이벤트 처리)
function updateDice(user) {
  // 우선, 기존 주사위들 모두 제거 후 renderUsers를 호출해도 되지만
  // 최적화를 위해 이동한 사용자만 갱신하려면:
  document.querySelectorAll(".dice-container").forEach((el) => {
    const parentTile = el.parentElement;
    parentTile.innerHTML = parentTile.textContent.trim(); // 타일에 있던 score 되돌리기
    el.remove();
  });
  // 방 전체를 다시 렌더해도 되며, 간단히 다음을 호출:
  // socket 통해 방 사용자 목록을 최신으로 다시 요청할 수도 있지만,
  // 서버에서 'room_users' 메시지 브로드캐스트하게 설정하면 실시간 반영됨.
  // 여기서는 단순히 방 접속 당시 fetch했던 콘텐츠 다시 그리는 방식 선택
  // → 하지만 구현 복잡도를 줄이기 위해 renderUsers를 다시 호출하려면
  //    별도 users 배열을 유지해야 한다. 더 간단하게는 방 전체 재렌더링.
}

// 현재 보드 가로 크기 구하기
function getBoardWidth() {
  const board = document.getElementById("board");
  // grid-template-columns에서 px 정보 꺼내서 계산하거나, 서버에서 width를 전역 변수에 저장
  // 여기서는 처음 renderBoard 할 때 전역 변수에 저장하는 방식으로 수정하는 게 깔끔
  return boardWidth || 0;
}

function displayTurnOrder(order) {
  const el = document.getElementById("turn-order");
  const result = order.map(({ user, action }) => `${user}(${action})`);
  if (!result || result.length === 0) {
    el.textContent = "";
    return;
  }
  el.textContent = "Turn: " + result.join(" → ");
}

function rollDice(data, dir) {
  switch (dir) {
    case "up":
      return {
        top: data.front,
        bottom: data.back,
        left: data.left,
        right: data.right,
        front: data.bottom,
        back: data.top,
      };
    case "down":
      return {
        top: data.back,
        bottom: data.front,
        left: data.left,
        right: data.right,
        front: data.top,
        back: data.bottom,
      };
    case "left":
      return {
        top: data.top,
        bottom: data.bottom,
        left: data.front,
        right: data.back,
        front: data.right,
        back: data.left,
      };
    case "right":
      return {
        top: data.top,
        bottom: data.bottom,
        left: data.back,
        right: data.front,
        front: data.left,
        back: data.right,
      };
  }
  return data;
}

function setupDiceDrag(cube, onEnd) {
  let dragging = false;
  let startX = 0,
    startY = 0;
  let rotX = 0,
    rotY = 0;
  let dragStart = null;

  cube.addEventListener("mousedown", (e) => {
    dragging = true;
    startX = e.clientX;
    startY = e.clientY;
    dragStart = {
      x: e.clientX,
      y: e.clientY,
    };
    cube.style.transition = "none";
  });

  document.addEventListener("mousemove", (e) => {
    if (!dragging) return;
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    startX = e.clientX;
    startY = e.clientY;

    rotY += dx * 0.5;
    rotX -= dy * 0.5;
    rotX = Math.max(-45, Math.min(45, rotX));
    rotY = Math.max(-45, Math.min(45, rotY));
    cube.style.transform = `rotateX(${rotX}deg) rotateY(${rotY}deg)`;
  });

  document.addEventListener("mouseup", (e) => {
    if (!dragging) return;
    dragging = false;
    cube.style.transition = "transform 0.5s ease";
    cube.style.transform = "rotateX(0deg) rotateY(0deg)";
    const dx = e.clientX - dragStart.x;
    const dy = e.clientY - dragStart.y;
    dragStart = null;
    rotX = 0;
    rotY = 0;
    onEnd(dx, dy);
  });
}

// 주사위 DOM 생성 및 드래그 이벤트 처리 (기존 createDiceElement 함수 수정)
function createDiceElement(diceData, tileData, isMine = false) {
  tileData.textContent = "";

  const container = document.createElement("div");
  container.className = "dice-container";

  const cube = document.createElement("div");
  cube.className = "dice";

  for (const face of ["top", "bottom", "left", "right", "front", "back"]) {
    const f = document.createElement("div");
    f.className = `face ${face}`;
    f.style.background = colorMap[diceData[face]] || "white";
    cube.appendChild(f);
  }
  container.appendChild(cube);

  setupDiceDrag(cube, (dx, dy) => {
    if (!isMine) return;
    if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;

    const direction =
      Math.abs(dx) > Math.abs(dy)
        ? dx > 0
          ? "right"
          : "left"
        : dy > 0
        ? "down"
        : "up";

    ws.send(
      JSON.stringify({
        action: "move",
        room_id: roomId,
        user_id: myUserId,
        direction,
      })
    );
  });

  return container;
}

function enableStartSelection() {
  document.getElementById("start-dice").innerHTML = "";
  document.querySelectorAll(".tile.start").forEach((t) => {
    t.classList.add("selectable");
    t.addEventListener("click", handleStartClick);
  });
}

function disableStartSelection() {
  document.querySelectorAll(".tile.start").forEach((t) => {
    t.classList.remove("selectable");
    t.replaceWith(t.cloneNode(true));
  });
  document.getElementById("start-dice").innerHTML = "";
  if (selectedTile) {
    selectedTile.innerHTML = selectedTile.textContent.trim();
    selectedTile = null;
  }
}

let selectedTile = null;

function handleStartClick(e) {
  const tile = e.currentTarget;
  if (selectedTile && selectedTile !== tile) {
    selectedTile.innerHTML = selectedTile.textContent.trim();
  }
  selectedTile = tile;
  tile.textContent = "";

  const container = document.createElement("div");
  container.className = "dice-container";

  let cube = document.createElement("div");
  cube.className = "dice";
  for (const face of ["top", "bottom", "left", "right", "front", "back"]) {
    const faceDiv = document.createElement("div");
    faceDiv.className = `face ${face}`;
    faceDiv.style.background = colorMap[startDiceData[face]] || "white";
    cube.appendChild(faceDiv);
  }
  container.appendChild(cube);

  cube.addEventListener("dblclick", () => {
    ws.send(
      JSON.stringify({
        action: "set_start",
        room_id: roomId,
        user_id: myUserId,
        x: Number(tile.dataset.x),
        y: Number(tile.dataset.y),
        dice: startDiceData,
      })
    );
    disableStartSelection();
  });

  setupDiceDrag(cube, (dx, dy) => {
    if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
    const dir =
      Math.abs(dx) > Math.abs(dy)
        ? dx > 0
          ? "right"
          : "left"
        : dy > 0
        ? "down"
        : "up";

    startDiceData = rollDice(startDiceData, dir);
    cube.querySelectorAll(".face").forEach((f) => {
      const faceName = f.classList[1];
      f.style.background = colorMap[startDiceData[faceName]] || "white";
    });
  });

  tile.appendChild(container);
}

function setDiceState(user) {
  const tileIndex = (user.pos_y - 1) * getBoardWidth() + (user.pos_x - 1);
  const tile = document.getElementById("board").children[tileIndex];
  const cube = tile.querySelector(".dice");

  // 1. 기존 cube 요소를 복제 (이벤트 리스너는 복제되지 않음)
  const newCube = cube.cloneNode(true);
  // 2. 기존 cube를 새로운 cube로 교체
  cube.parentNode.replaceChild(newCube, cube);

  newCube.addEventListener("dblclick", () => {
    ws.send(
      JSON.stringify({
        action: "set_dice_state",
        room_id: roomId,
        user_id: myUserId,
        x: Number(tile.dataset.x),
        y: Number(tile.dataset.y),
        dice: startDiceData,
      })
    );
    disableStartSelection();
  });

  setupDiceDrag(newCube, (dx, dy) => {
    if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
    const dir =
      Math.abs(dx) > Math.abs(dy)
        ? dx > 0
          ? "right"
          : "left"
        : dy > 0
        ? "down"
        : "up";

    startDiceData = rollDice(startDiceData, dir);
    newCube.querySelectorAll(".face").forEach((f) => {
      const faceName = f.classList[1];
      f.style.background = colorMap[startDiceData[faceName]] || "white";
    });
  });
}

// 다른 플레이어의 주사위를 굴릴 수 있는 권한 (targetMove)
// 서버에는 아직 target_move 액션이 완전히 구현되지 않았지만,
// 클라이언트에서 해당 주사위를 드래그하여 방향을 선택하고
// 서버로 { action: 'target_move', room_id, user_id, target_user_id, direction } 형태로 전송한다.
// msg.user 파라미터에는 대상 유저의 정보가 전달된다고 가정한다.
function targetMove(user) {
  const board = document.getElementById("board");
  const allDice = board.querySelectorAll(".dice");
  if (!allDice) {
    console.warn("targetMove: dice element not found");
    return;
  }

  allDice.forEach((cube) => {
    console.log(cube);
    if (
      user.dice.front !== "yellow" &&
      user.dice.front !== cube.querySelector(".face.front").style.background
    ) {
      return;
    }
    // 기존 cube 교체하여 drag 이벤트 초기화
    const newCube = cube.cloneNode(true);
    cube.parentNode.replaceChild(newCube, cube);
    tile = cube.parentNode.parentNode;

    // 강조 표시
    tile.classList.add("selectable");

    setupDiceDrag(newCube, (dx, dy) => {
      if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
      const direction =
        Math.abs(dx) > Math.abs(dy)
          ? dx > 0
            ? "right"
            : "left"
          : dy > 0
          ? "down"
          : "up";

      ws.send(
        JSON.stringify({
          action: "target_move",
          room_id: roomId,
          user_id: myUserId, // 명령을 내리는 유저 (나)
          target_user_id: target.user || target.user_id || target.id, // 실제로 움직일 대상 유저
          direction,
        })
      );

      // 한번 전송 후 더 이상 드래그 안 되도록 제거 (원한다면)
      newCube.replaceWith(newCube.cloneNode(true));
      tile.classList.remove("selectable");
    });
  });
}

function handleAction(action) {
  switch (action) {
    case "goBack":
      history.back();
      break;
    case "goHome":
      window.location.href = "/";
      break;
    default:
      console.warn("Unknown action:", action);
  }
}

// helper functions (renderBoard, renderUsers, updateDice, etc.) moved unchanged from original script
// Due to space, they are assumed present; copy them from previous inline code if needed.
