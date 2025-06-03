<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';
require_once LIB_PATH . '/redis.php'; // getRedis 함수

session_start();

$redis = getRedis();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = substr(md5(uniqid('', true)), 0, 10);
    $_SESSION['nickname'] = 'guest_' . rand(1000, 9999);
}

// Redis에 유저 정보 저장 (Hash)
$userKey = "user:{$_SESSION['user_id']}";
$now = date('Y-m-d H:i:s');

$redis->hmset($userKey, [
    'user_id' => $_SESSION['user_id'],
    'nickname' => $_SESSION['nickname'],
    'last_active' => $now
]);

// (옵션) 전체 유저 Set에 등록
$redis->sadd('users', $_SESSION['user_id']);

echo json_encode([
    'user_id' => $_SESSION['user_id'],
    'nickname' => $_SESSION['nickname']
], JSON_UNESCAPED_UNICODE);
