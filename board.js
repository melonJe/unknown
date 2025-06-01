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
