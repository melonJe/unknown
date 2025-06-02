CREATE TABLE users (
  user_id VARCHAR(20) PRIMARY KEY,
  nickname VARCHAR(50) NOT NULL,
  last_active TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS rooms (
	room_id varchar(10) PRIMARY KEY,
	board jsonb NOT NULL,
	created_at timestamp DEFAULT now() NULL,
	update_at timestamp DEFAULT now() NULL
);

CREATE TABLE room_users (
    room_id   VARCHAR(20) NOT NULL,
    user_id   VARCHAR(20) NOT NULL,
    pos_x     INTEGER NOT NULL DEFAULT 0,
    pos_y     INTEGER NOT NULL DEFAULT 0,
    dice      JSONB NOT NULL DEFAULT '[]',
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE room_turns (
    room_id VARCHAR(20) PRIMARY KEY,
    current_turn_user_id VARCHAR(20) NOT NULL,
    updated_at TIMESTAMP DEFAULT now(),

    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (current_turn_user_id) REFERENCES users(user_id) ON DELETE CASCADE
);