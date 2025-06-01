<?php

$env = [];
$lines = file(__DIR__ . '/../.env');
foreach ($lines as $line) {
    if (preg_match('/^\s*#/', $line))
        continue;
    if (preg_match('/^\s*([\w_]+)\s*=\s*(.*?)\s*$/', $line, $matches)) {
        $env[$matches[1]] = trim($matches[2], "\"'");
    }
}
