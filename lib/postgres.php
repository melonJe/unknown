<?php

function getPdo(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = 'pgsql:host=' . $_ENV['DB_HOST'] . ';port=' . $_ENV['DB_PORT'] . ';dbname=' . $_ENV['DB_DATABASE'] . ';';
    $user = $_ENV['DB_USERNAME'];
    $password = $_ENV['DB_PASSWORD'];

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (Exception $e) {
        file_put_contents(BASE_PATH . '/debug.log', "[DB ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode([
            "error" => "DB 연결 오류",
            "message" => $e->getMessage()
        ]);
        exit;
    }
}
