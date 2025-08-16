<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Predis
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
// Do not fail if .env is missing (Docker supplies env via env_file)
$dotenv->safeLoad();

function getRedis()
{
    static $redis = null;
    if ($redis === null) {
        // Prefer Docker-provided env vars; fall back to .env values or sensible defaults
        $host = getenv('REDIS_HOST');
        if (!$host) { $host = $_ENV['REDIS_HOST'] ?? 'redis'; }
        $port = getenv('REDIS_PORT');
        if (!$port) { $port = $_ENV['REDIS_PORT'] ?? '6379'; }
        $password = getenv('REDIS_PASSWORD');
        if ($password === false || $password === null || $password === '') { $password = $_ENV['REDIS_PASSWORD'] ?? null; }

        $redis = new Predis\Client([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => (int)$port,
            // 'username' => $_ENV['REDIS_USERNAME'],
            'password' => $password,
        ]);
    }
    return $redis;
}
