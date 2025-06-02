<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use Models\User;

session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = substr(md5(uniqid()), 0, 10);
    $_SESSION['nickname'] = 'guest_' . rand(1000, 9999);
}

// DB에 등록 또는 갱신
User::updateOrCreate(
    ['user_id' => $_SESSION['user_id']],
    [
        'nickname' => $_SESSION['nickname'],
        'last_active' => date('Y-m-d H:i:s')
    ]
);

echo json_encode([
    'user_id' => $_SESSION['user_id'],
    'nickname' => $_SESSION['nickname']
]);
