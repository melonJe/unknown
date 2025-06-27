CREATE TABLE IF NOT EXISTS map (
  map_id VARCHAR(20) PRIMARY KEY,
  board jsonb NOT NULL
);

-- 로그 테이블: 사용자 이동 턴
CREATE TABLE IF NOT EXISTS move_turns (
  id SERIAL PRIMARY KEY,
  room_id VARCHAR(20) NOT NULL,
  user_id VARCHAR(20) NOT NULL,
  action VARCHAR(20) NOT NULL,
  direction VARCHAR(10),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 로그 테이블: 히든 룰로 추가된 턴
CREATE TABLE IF NOT EXISTS hidden_turns (
  id SERIAL PRIMARY KEY,
  room_id VARCHAR(20) NOT NULL,
  user_id VARCHAR(20) NOT NULL,
  action VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);