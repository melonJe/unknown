fetch('/api/get_board.php')
    .then(res => res.json())
    .then(data => renderBoard(data));

function renderBoard(data) {
    const board = document.getElementById('board');

    // 스타일 반응형 적용
    board.style.gridTemplateColumns = `repeat(${data.width}, 40px)`;
    board.style.gridTemplateRows = `repeat(${data.height}, 40px)`;
    const boardWidth = board.width;
    document.documentElement.style.setProperty('--cols', boardWidth);

    // 타일 맵 초기화
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
                if (tile.score !== undefined) {
                    div.textContent = tile.score;
                }
            } else {
                div.style.backgroundColor = '#eee';
            }

            board.appendChild(div);
        }
    }
}

