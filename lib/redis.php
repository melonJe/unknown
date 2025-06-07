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
            'username' => $_ENV['REDIS_USERNAME'],
            'password' => $_ENV['REDIS_PASSWORD'],
        ]);
    }
    return $redis;
}
