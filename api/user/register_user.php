<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php'; // getRedis 함수

session_start();

$redis = getRedis();

$userId = $_SESSION['user_id'] ?? null;

if (!isset($userId)) {
    $_SESSION['user_id'] = substr(md5(uniqid('', true)), 0, 10);
    $userId = $_SESSION['user_id']; // ensure local variable is populated
}

// Redis에 유저 정보 저장 (Hash)
$userKey = "user:{$userId}";
$now = date('Y-m-d H:i:s');
$redis->hmset($userKey, ['user_id' => $userId]);
$redis->expire($userKey, 60 * 60 * 24);

// (옵션) 전체 유저 Set에 등록
$redis->sadd('users', $userId);

echo json_encode([
    'user_id' => $userId,
], JSON_UNESCAPED_UNICODE);
