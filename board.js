fetch('/api/user/init.php')
    .then(res => res.json())
    .then(user => {
        console.log('내 정보:', user);
    });

fetch('/api/user/list.php')
    .then(res => res.json())
    .then(({ users }) => {
        console.log('전체 유저:', users);
    });
window.addEventListener('DOMContentLoaded', () => {
    loadRoomUsers(roomId);
});

async function loadRoomUsers(roomId) {
    const res = await fetch(`/api/room/get_room_users.php?room_id=${roomId}`);
    const { users } = await res.json();

    for (const user of users) {
        const { pos_x, pos_y, dice } = user;

        const tileIndex = (pos_y - 1) * 23 + (pos_x - 1);
        const tile = board.children[tileIndex];

        const diceEl = createDiceElement(dice);
        tile.appendChild(diceEl);
    }
}

function renderBoard(data) {
    const board = document.getElementById('board');
    board.innerHTML = '';
    board.style.gridTemplateColumns = `repeat(${data.width}, 40px)`;
    board.style.gridTemplateRows = `repeat(${data.height}, 40px)`;
    const boardWidth = board.width;
    document.documentElement.style.setProperty('--cols', boardWidth);

    const tileMap = Array.from({ length: data.height }, () =>
        Array.from({ length: data.width }, () => null)
    );

    for (const tile of data.tiles) {
        tileMap[tile.y - 1][tile.x - 1] = tile;
    }

    for (let y = 0; y < data.height; y++) {
        for (let x = 0; x < data.width; x++) {
            const tile = tileMap[y][x];
            const div = document.createElement('div');
            div.className = 'tile';

            if (tile) {
                div.classList.add(tile.type);
                if (tile.type === 'floor' && tile.color) {
                    div.classList.add(tile.color);
                }
                if ('score' in tile) {
                    div.textContent = tile.score;
                }
            } else {
                div.style.backgroundColor = '#eee';
            }

            board.appendChild(div);
        }
    }
}

function createDiceElement(diceData) {
    const container = document.createElement('div');
    container.className = 'dice-container';

    const cube = document.createElement('div');
    cube.className = 'dice';

    for (const face of ['top', 'bottom', 'left', 'right', 'front', 'back']) {
        const f = document.createElement('div');
        f.className = `face ${face}`;
        f.style.background = diceData[face] || 'white'; // 기본 배경
        cube.appendChild(f);
    }

    container.appendChild(cube);

    let dragging = false;
    let startX = 0, startY = 0;
    let rotX = 0, rotY = 0;
    let dragStart = null;

    container.addEventListener('mousedown', (e) => {
        dragging = true;
        startX = e.clientX;
        startY = e.clientY;
        dragStart = { x: e.clientX, y: e.clientY };
        cube.style.transition = 'none';
    });

    document.addEventListener('mousemove', (e) => {
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

    document.addEventListener('mouseup', async (e) => {
        if (!dragging) return;
        dragging = false;

        cube.style.transition = 'transform 0.5s ease';
        cube.style.transform = `rotateX(0deg) rotateY(0deg)`;
        rotX = 0;
        rotY = 0;

        if (!dragStart) return;
        const dragEnd = { x: e.clientX, y: e.clientY };
        const dx = dragEnd.x - dragStart.x;
        const dy = dragEnd.y - dragStart.y;

        if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return; // 너무 짧으면 무시

        let direction;
        if (Math.abs(dx) > Math.abs(dy)) {
            direction = dx > 0 ? 'right' : 'left';
        } else {
            direction = dy > 0 ? 'down' : 'up';
        }

        try {
            const res = await fetch('/api/dice/move.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                credentials: 'include',
                body: new URLSearchParams({
                    room_id: roomId,
                    direction
                })
            });

            const result = await res.json();

            if (!res.ok) {
                console.error('주사위 이동 실패:', result);
                return;
            }
            console.error(result);
            // 성공 시 UI 갱신 등 작업
            document.querySelectorAll('.dice-container').forEach(el => el.remove());
            loadRoomUsers(roomId);

        } catch (err) {
            console.error('요청 에러:', err);
        }

        dragStart = null;
    });

    return container;
}
