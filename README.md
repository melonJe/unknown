# 🎲 실시간 보드게임

PHP 8 기반의 보드게임 unknown입니다.

플레이어는 로비에서 방을 생성하거나 참가한 뒤, unknown 게임을 진행합니다. **Workerman** WebSocket·**Redis**·**PostgreSQL**가 실시간 통신과 상태 관리를 담당합니다.

---

## 📂 프로젝트 구조

```
├── api/               👉 Stateless REST / AJAX helpers
│   └── user/
├── DAO/               👉 Low-level DB access objects
├── DTO/               👉 Typed data-transfer objects
├── Service/           👉 Domain logic (Room, User, Dice, Turn …)
├── Helpers/           👉 Misc helpers (env, validators …)
├── server.php         👉 Workerman WebSocket server (port 8080)
├── index.php          👉 Lobby (HTTP)
├── game.php           👉 Board UI (HTTP)
├── style.css          👉 Shared UI styles
├── docker-compose.yml 👉 Local Redis + Postgres stack
└── .env*              👉 Runtime configuration (see below)
```

---

## 🛠️ 기술 스택

- PHP 8 + Composer
- [Workerman](https://github.com/walkor/Workerman) — high-performance WebSocket server
- Redis — fast in-memory message & state store (Predis client)
- PostgreSQL — persistent game data
- Docker / docker-compose — painless local infra
- HTML / Vanilla JS Frontend (no framework)

---

## 🚀 빠른 시작

1. **클론 및 의존성 설치**
   ```bash
   git clone <this-repo>
   cd <this-repo>
   composer install
   ```
2. **환경 변수 설정** — 샘플 파일 복사 후 필요 시 수정:
   ```bash
   cp .env.example .env
   cp .env.redis.example .env.redis
   cp .env.postgres.example .env.postgres
   ```
3. **Redis & Postgres 기동** (백그라운드):
   ```bash
   docker compose up -d
   ```
4. **WebSocket 서버 실행** (포트 8080):
   ```bash
   php server.php start
   ```
   데몬으로 실행하려면 `php server.php start -d` 를 사용하세요.
5. **로비 UI 제공** (아무 PHP 서버면 동작):
   ```bash
   php -S 127.0.0.1:8000
   ```
6. 브라우저에서 `http://localhost:8000` 접속 → 방 생성 → 다른 브라우저 창 열기 → 플레이!

---

## ⚙️ 환경 변수(예시)

| Key              | Description             | Example     |
| ---------------- | ----------------------- | ----------- |
| `REDIS_HOST`     | Redis hostname          | `127.0.0.1` |
| `REDIS_PASSWORD` | Redis auth (_optional_) | `password`  |
| `PG_HOST`        | PostgreSQL hostname     | `127.0.0.1` |
| `PG_DATABASE`    | DB name                 | `boardgame` |
| `PG_USER`        | DB user                 | `postgres`  |
| `PG_PASSWORD`    | DB password             | `password`  |

> `docker-compose.yml` 과 `*.example` 파일에 로컬 개발용 기본값이 포함되어 있습니다.

---

## 기능 구현 예정

- 턴 제한시간 구현
- message alert이 아닌 toast 구현
- 메인페이지 게임 룰 설명 추가

---

## 📜 라이선스

MIT © 2025 melonJe
