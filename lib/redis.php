<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Predis
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
function getRedis()
{
    static $redis = null;
    if ($redis === null) {
        $redis = new Predis\Client([
            'scheme' => 'tcp',
            'host' => $_ENV['REDIS_HOST'],
            'port' => $_ENV['REDIS_PORT'],
            // 'password' => '비밀번호', // 필요한 경우
        ]);
    }
    return $redis;
}
