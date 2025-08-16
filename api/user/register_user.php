<?php

header('Content-Type: application/json; charset=utf-8');
// Prevent PHP warnings/notices from corrupting JSON output
ini_set('display_errors', '0');

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/redis.php'; // getRedis 함수

session_start();

$redis = getRedis();

// Ensure a user_id exists in the current session and also assign it locally
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = substr(md5(uniqid('', true)), 0, 10);
}
$userId = $_SESSION['user_id'];

// Redis에 유저 정보 저장 (Hash)
$userKey = "user:{$userId}";
$now = date('Y-m-d H:i:s');
$redis->hmset($userKey, ['user_id' => $userId]);
$redis->expire($userKey, 60 * 60 * 24);

// (옵션) 전체 유저 Set에 등록
$redis->sadd('users', $userId);

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'user_id' => $userId,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
