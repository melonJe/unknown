document.addEventListener("DOMContentLoaded", () => {
  const wsUrl = document.body.dataset.wsUrl;
  const createBtn = document.getElementById("createRoomBtn");

  let myUserId;
  let ws;

  fetch("/api/user/register_user.php")
    .then((res) => res.json())
    .then((user) => {
      myUserId = user.user_id;
      ws = new WebSocket(`${wsUrl}?userId=${myUserId}`);

      ws.onopen = () => ws.send(JSON.stringify({ action: "get_room_list" }));
      ws.onmessage = (e) => handleMessage(JSON.parse(e.data));
      ws.onerror = () => ws.close();
    });

  function handleMessage(msg) {
    switch (msg.type) {
      case "room_list":
        renderRoomList(msg.rooms);
        break;
      case "room_created":
        location.href = `game.php?room_id=${msg.room_id}`;
        break;
      case "room_list_changed":
        ws.send(JSON.stringify({ action: "get_room_list" }));
        break;
    }
  }

  function renderRoomList(rooms) {
    const list = document.getElementById("room-list");
    list.textContent = "";
    if (!rooms || !Object.keys(rooms).length) {
      list.innerHTML = "<li>현재 참여 가능한 방이 없습니다.</li>";
      return;
    }
    const frag = document.createDocumentFragment();
    Object.entries(rooms)
      .sort(([, a], [, b]) => (a === "1") - (b === "1"))
      .forEach(([id, started]) => {
        const li = document.createElement("li");
        if (started !== "1") {
          const a = document.createElement("a");
          a.href = `game.php?room_id=${encodeURIComponent(id)}`;
          a.textContent = `▶ 방 #${id}`;
          li.appendChild(a);
        } else {
          li.textContent = `▶ 방 #${id}`;
          li.classList.add("disabled-room");
        }
        frag.appendChild(li);
      });
    list.appendChild(frag);
  }

  createBtn.addEventListener("click", () => {
    ws.send(JSON.stringify({ action: "create_room", user_id: myUserId }));
  });
});
