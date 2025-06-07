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
    <div id="board"></div>

    <script>
        let roomId = "<?= $room_id ?>";
        let myUserId;
        let boardWidth;
        let boardData;
        let ws;
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
                console.log("ws://localhost:8080/?roomId=" + roomId + "&userId=" + myUserId);

                ws = new WebSocket("ws://localhost:8080/?roomId=" + roomId + "&userId=" + myUserId);

                ws.onopen = () => {
                    console.log('웹소켓 연결됨');
                    console.log('[방법 A] 유저 ID:', myUserId);
                    ws.send(JSON.stringify({
                        action: 'join_room',
                        room_id: roomId,
                        user_id: myUserId
                    }));
                };

                ws.onmessage = (event) => {
                    const msg = JSON.parse(event.data);

                    switch (msg.type) {
                        case 'board_data':
                            // 초기 보드 렌더링
                            boardData = msg.board
                            renderBoard(boardData);
                            break;
                        case 'user_out':

                        case 'dices_data':
                            // 주사위 렌더링
                            renderBoard(boardData);
                            renderUsers(msg.dices.player);
                            break;
                        case 'dice_moved':
                            // 한 사용자가 주사위 이동했을 때 해당 사용자 정보만 업데이트
                            updateDice(msg.user);
                            break;
                        case 'error':
                            // 서버에서 보낸 오류 메시지 표시 (예: 턴 아님)
                            alert(msg.message);
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

            // users가 { user_id: { pos_x, pos_y, dice }, ... } 형태라고 가정
            for (const [user_id, userData] of Object.entries(users)) {
                const {
                    dice,
                    pos_x,
                    pos_y
                } = userData;
                const tileIndex = (pos_y - 1) * getBoardWidth() + (pos_x - 1);
                const tile = document.getElementById('board').children[tileIndex];
                const isMine = (user_id === myUserId);
                const diceEl = createDiceElement(dice, tile, isMine);
                tile.appendChild(diceEl);
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

        // 주사위 DOM 생성 및 드래그 이벤트 처리 (기존 createDiceElement 함수 수정)
        function createDiceElement(diceData, tileData, isMine = false) {
            const originalScore = tileData.textContent; // 점수 저장
            tileData.textContent = '';

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
                if (!isMine) return;
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
    </script>
</body>

</html>