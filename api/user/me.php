<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "not initialized"]);
    exit;
}

echo json_encode([
    'user_id' => $_SESSION['user_id'],
    'nickname' => $_SESSION['nickname']
]);
