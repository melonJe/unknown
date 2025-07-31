<?php

$env = [];

// 우선 시스템 환경변수에서 가져오기
foreach ($_SERVER as $key => $value) {
    if (preg_match('/^[\w_]+$/', $key)) {
        $env[$key] = $value;
    }
}

// 시스템 환경변수에 없으면 .env 파일에서 읽기
$envFilePath = __DIR__ . '/../.env';
if (file_exists($envFilePath)) {
    $lines = file($envFilePath);
    foreach ($lines as $line) {
        if (preg_match('/^\s*#/', $line)) continue;
        if (preg_match('/^\s*([\w_]+)\s*=\s*(.*?)\s*$/', $line, $matches)) {
            $key = $matches[1];
            // 이미 시스템 환경변수에 있으면 덮어쓰지 않음
            if (!isset($env[$key])) {
                $env[$key] = trim($matches[2], "\"'");
            }
        }
    }
}

