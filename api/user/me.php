<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = substr(md5(uniqid('', true)), 0, 10);
    echo json_encode([
        'user_id' => $_SESSION['user_id']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'user_id' => $_SESSION['user_id']
], JSON_UNESCAPED_UNICODE);
