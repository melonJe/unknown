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
    <button id="startBtn">Start Game</button>
    <div id="board"></div>
    <div id="turn-order"></div>
    <div id="start-dice"></div>

    <script>
        let roomId = "<?= $room_id ?>";
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
            back: 'purple'
        };
        let startDiceEl;
        // 주사위 색 매핑
        const colorMap = {
            red: '#BF2C47',
            blue: '#0468BF',
            yellow: '#D9C24E',
            green: '#03A678',
            purple: '#8B65BF',
            white: 'white'
        };

        (async function() {
            try {
                myUserId = await getUserId();
                ws = new WebSocket("ws://localhost:8080/?roomId=" + roomId + "&userId=" + myUserId);

                ws.onopen = () => {
                    console.log('웹소켓 연결됨');
                    console.log('[방법 A] 유저 ID:', myUserId);
                    ws.send(JSON.stringify({
                        action: 'join_room',
                        room_id: roomId,
                        user_id: myUserId
                    }));
                    document.getElementById('startBtn').onclick = () => {
                        ws.send(JSON.stringify({
                            action: 'next_turn',
                            room_id: roomId,
                            user_id: myUserId
                        }));
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
                            if (msg.board) {
                                boardData = msg.board;
                            }
                            renderBoard(boardData);
                            renderUsers(msg.dices.player);
                            break;

                        case 'next_turn':
                            displayTurnOrder(msg.turn_order);
                            currentUser = msg.turn_order[0];

                            if (currentUser.user === myUserId && currentUser.action === 'setStartTile') {
                                enableStartSelection();
                            }
                            break;

                        case 'dice_moved':
                            updateDice(msg.user);
                            break;

                        case 'error':
                            alert(msg.message);
                            if (msg.action) {
                                handleAction(msg.action);
                            }
                            break;

                        default:
                            console.log('알 수 없는 메시지:', msg);
                    }
                };

                ws.onclose = () => {
                    console.log('웹소켓 연결 끊김');
                };

                ws.onerror = (e) => {
                    console.log('웹소켓 오류:', e);
                    ws.close();
                };
            } catch (error) {
                console.error('유저 정보 요청 실패:', error);
                return; // ID를 못 받아오면 이후 로직을 실행하지 않음
            }
        })();

        async function getUserId() {
            const res = await fetch('/api/user/me.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                credentials: 'include',
            });

            // 응답을 JSON으로 파싱
            const data = await res.json();
            return data.user_id;
        }

        // 보드 렌더 함수 (기존 renderBoard 함수와 동일)
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

            // 2차원 배열로 타일 정보 매핑
            const tileMap = Array.from({
                    length: data.height
                }, () =>
                Array.from({
                    length: data.width
                }, () => null)
            );
            for (const tile of data.tiles) {
                tileMap[tile.y - 1][tile.x - 1] = tile;
            }

            for (let y = 0; y < data.height; y++) {
                for (let x = 0; x < data.width; x++) {
                    const tile = tileMap[y][x];
                    const div = document.createElement('div');
                    div.className = 'tile';

                    // x, y 좌표 dataset에 저장
                    div.dataset.x = x + 1; // 1부터 시작하고 싶으면 +1
                    div.dataset.y = y + 1;

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

        function renderUsers(users) {
            document.querySelectorAll('.dice-container').forEach(el => el.remove());
            for (const [user_id, userData] of Object.entries(users)) {
                const {
                    dice,
                    pos_x,
                    pos_y
                } = userData;
                if (pos_x > 0 && pos_y > 0) {
                    const tileIndex = (pos_y - 1) * getBoardWidth() + (pos_x - 1);
                    const tile = document.getElementById('board').children[tileIndex];
                    const isMine = (user_id === myUserId);
                    const diceEl = createDiceElement(dice, tile, isMine);
                    tile.appendChild(diceEl);
                }
            }
        }


        // 단일 사용자 주사위 정보 업데이트 (move 이벤트 처리)
        function updateDice(user) {
            // 우선, 기존 주사위들 모두 제거 후 renderUsers를 호출해도 되지만
            // 최적화를 위해 이동한 사용자만 갱신하려면:
            document.querySelectorAll('.dice-container').forEach(el => {
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
            const board = document.getElementById('board');
            // grid-template-columns에서 px 정보 꺼내서 계산하거나, 서버에서 width를 전역 변수에 저장
            // 여기서는 처음 renderBoard 할 때 전역 변수에 저장하는 방식으로 수정하는 게 깔끔
            return boardWidth || 0;
        }

        function displayTurnOrder(order) {
            const el = document.getElementById('turn-order');
            if (!order || order.length === 0) {
                el.textContent = '';
                return;
            }
            el.textContent = 'Turn: ' + order.join(' → ');
        }

        function rollDice(data, dir) {
            switch (dir) {
                case 'up':
                    return {
                        top: data.front,
                            bottom: data.back,
                            left: data.left,
                            right: data.right,
                            front: data.bottom,
                            back: data.top
                    };
                case 'down':
                    return {
                        top: data.back,
                            bottom: data.front,
                            left: data.left,
                            right: data.right,
                            front: data.top,
                            back: data.bottom
                    };
                case 'left':
                    return {
                        top: data.top,
                            bottom: data.bottom,
                            left: data.front,
                            right: data.back,
                            front: data.right,
                            back: data.left
                    };
                case 'right':
                    return {
                        top: data.top,
                            bottom: data.bottom,
                            left: data.back,
                            right: data.front,
                            front: data.left,
                            back: data.right
                    };
            }
            return data;
        }

        // 주사위 DOM 생성 및 드래그 이벤트 처리 (기존 createDiceElement 함수 수정)
        function createDiceElement(diceData, tileData, isMine = false) {
            tileData.textContent = "";

            const container = document.createElement('div');
            container.className = 'dice-container';

            const cube = document.createElement('div');
            cube.className = 'dice';

            for (const face of ['top', 'bottom', 'left', 'right', 'front', 'back']) {
                const f = document.createElement('div');
                f.className = `face ${face}`;
                f.style.background = colorMap[diceData[face]] || 'white';
                cube.appendChild(f);
            }
            container.appendChild(cube);

            let dragging = false;
            let startX = 0,
                startY = 0;
            let rotX = 0,
                rotY = 0;
            let dragStart = null;

            container.addEventListener('mousedown', (e) => {
                // if (!isMine) return;
                dragging = true;
                startX = e.clientX;
                startY = e.clientY;
                dragStart = {
                    x: e.clientX,
                    y: e.clientY
                };
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
                if (!isMine) return;

                if (!dragStart) return;
                const dx = e.clientX - dragStart.x;
                const dy = e.clientY - dragStart.y;

                if (Math.abs(dx) < 50 && Math.abs(dy) < 50) {
                    dragStart = null;
                    return;
                }

                // 드래그 방향 판별
                let direction;
                if (Math.abs(dx) > Math.abs(dy)) {
                    direction = dx > 0 ? 'right' : 'left';
                } else {
                    direction = dy > 0 ? 'down' : 'up';
                }

                // WebSocket으로 move 메시지 전송
                ws.send(JSON.stringify({
                    action: 'move',
                    room_id: roomId,
                    user_id: myUserId,
                    direction: direction
                }));

                dragStart = null;
            });

            return container;
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

        let selectedTile = null;

        function handleStartClick(e) {
            const tile = e.currentTarget;
            tile.textContent = '';

            // 주사위 컨테이너 생성
            const container = document.createElement('div');
            container.className = 'dice-container';

            // 주사위 DOM 생성
            let cube = document.createElement('div');
            cube.className = 'dice';
            for (const face of ['top', 'bottom', 'left', 'right', 'front', 'back']) {
                const faceDiv = document.createElement('div');
                faceDiv.className = `face ${face}`;
                faceDiv.style.background = colorMap[startDiceData[face]] || 'white';
                cube.appendChild(faceDiv);
            }
            container.appendChild(cube);

            // 회전 상태 변수
            let isDragging = false;
            let startX = 0,
                startY = 0;
            let rotX = 0,
                rotY = 0;

            container.addEventListener('mousedown', e => {
                console.log("mousedown on cube");
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                cube.style.transition = 'none';

                const onMouseMove = e => {
                    if (!isDragging) return;
                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;
                    startX = e.clientX;
                    startY = e.clientY;
                    rotY += dx * 0.5;
                    rotX -= dy * 0.5;
                    rotY = Math.max(-45, Math.min(45, rotY));
                    rotX = Math.max(-45, Math.min(45, rotX));
                    cube.style.transform = `rotateX(${rotX}deg) rotateY(${rotY}deg)`;
                };

                const onMouseUp = e => {
                    if (!isDragging) return;
                    isDragging = false;

                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;

                    if (Math.abs(dx) < 50 && Math.abs(dy) < 50) {
                        // 클릭 → 서버 전송
                        ws.send(JSON.stringify({
                            action: 'set_start',
                            room_id: roomId,
                            user_id: myUserId,
                            x: Number(tile.dataset.x),
                            y: Number(tile.dataset.y),
                            dice: diceData
                        }));
                    } else {
                        // 드래그 → 주사위 방향 회전 후 교체
                        const dir = Math.abs(dx) > Math.abs(dy) ?
                            (dx > 0 ? 'right' : 'left') :
                            (dy > 0 ? 'down' : 'up');

                        startDiceData = rollDice(startDiceData, dir);

                        // 주사위 교체
                        container.removeChild(cube);
                        cube = document.createElement('div');
                        cube.className = 'dice';
                        for (const face of ['top', 'bottom', 'left', 'right', 'front', 'back']) {
                            const faceDiv = document.createElement('div');
                            faceDiv.className = `face ${face}`;
                            faceDiv.style.background = colorMap[startDiceData[face]] || 'white';
                            cube.appendChild(faceDiv);
                        }
                        container.appendChild(cube);
                    }

                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                };

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });

            tile.appendChild(container);
            disableStartSelection();
        }

        // function handleStartDiceClick(e) {
        //     const tile = e.currentTarget;
        //     const idx = Array.prototype.indexOf.call(tile.parentNode.children, tile);
        //     const x = (idx % boardWidth) + 1;
        //     const y = Math.floor(idx / boardWidth) + 1;
        //     ws.send(JSON.stringify({
        //         action: 'set_start',
        //         room_id: roomId,
        //         user_id: myUserId,
        //         x: x,
        //         y: y,
        //         dice: startDiceData
        //     }));
        //     disableStartSelection();
        // }

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
    </script>
</body>

</html>